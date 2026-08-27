<?php
/** Local market-data imports: administrators validate an entire CSV before upserting price references, retain source context, and never persist the uploaded file or fabricated records. */
declare(strict_types=1);

function market_price_imports_are_available(): bool
{
    try {
        $row = fetch_one('SELECT COUNT(*) AS count FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name', ['table_name' => 'market_price_import_batches']);
        return (int) ($row['count'] ?? 0) === 1;
    } catch (Throwable $exception) {
        return false;
    }
}

function market_price_import_text(string $value, int $maxLength): string
{
    $value = normalize_text($value, $maxLength);
    if (preg_match('/^[=+\-@]/', $value)) {
        throw new RuntimeException('Spreadsheet formula-like values are not accepted in market-data imports.');
    }
    return $value;
}

function market_price_import_decimal(string $value, string $label): float
{
    $value = trim(str_replace(',', '', $value));
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value) || (float) $value < 0) {
        throw new RuntimeException('Enter a non-negative ' . $label . ' with up to two decimal places.');
    }
    return (float) $value;
}

function market_price_import_date(string $value): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
    if ($date === false || $date->format('Y-m-d') !== trim($value)) {
        throw new RuntimeException('Use a price date in YYYY-MM-DD format.');
    }
    return $date->format('Y-m-d');
}

function market_price_import_csv(int $administratorId, array $file, array $input): array
{
    if (!market_price_imports_are_available()) {
        throw new RuntimeException('Market-data import is not ready. Import database/migrations/20260827_add_market_price_imports.sql, then refresh this page.');
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        throw new RuntimeException('Choose a CSV file from this computer. The file is validated in memory and is not retained by the application.');
    }
    if ((int) ($file['size'] ?? 0) < 1 || (int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
        throw new RuntimeException('Use a non-empty CSV file no larger than 2 MB.');
    }

    $sourceName = market_price_import_text((string) ($input['source_name'] ?? ''), 160);
    $sourceReference = market_price_import_text((string) ($input['source_reference'] ?? ''), 255);
    if ($sourceName === '') {
        throw new RuntimeException('Name the accountable local source for these price records.');
    }

    $handle = fopen((string) $file['tmp_name'], 'rb');
    if ($handle === false) {
        throw new RuntimeException('The selected CSV file could not be read.');
    }
    $expectedHeaders = ['product', 'district', 'minimum_price', 'maximum_price', 'average_price', 'unit', 'price_date', 'notes'];
    $header = fgetcsv($handle);
    if ($header === false) {
        fclose($handle);
        throw new RuntimeException('The CSV file must include the required header row.');
    }
    $headers = array_map(static fn($value): string => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $value) ?? '')), $header);
    if ($headers !== $expectedHeaders) {
        fclose($handle);
        throw new RuntimeException('Use this exact header order: product,district,minimum_price,maximum_price,average_price,unit,price_date,notes.');
    }

    $rows = [];
    $errors = [];
    $rowNumber = 1;
    while (($row = fgetcsv($handle)) !== false) {
        $rowNumber += 1;
        if ($row === [null] || $row === []) {
            continue;
        }
        if (count($row) !== count($expectedHeaders)) {
            $errors[] = 'Row ' . $rowNumber . ': expected ' . count($expectedHeaders) . ' columns.';
            continue;
        }
        try {
            [$product, $district, $minimum, $maximum, $average, $unit, $priceDate, $notes] = $row;
            $product = market_price_import_text((string) $product, 120);
            $district = market_price_import_text((string) $district, 100);
            $unit = strtolower(market_price_import_text((string) $unit, 30));
            $notes = market_price_import_text((string) $notes, 500);
            if ($product === '' || $district === '' || $unit === '') {
                throw new RuntimeException('Product, district, and unit are required.');
            }
            if (!preg_match('/^[a-z][a-z _-]{0,29}$/', $unit)) {
                throw new RuntimeException('Use a plain-text unit such as kg, crate, bag, or tonne.');
            }
            $minimum = market_price_import_decimal((string) $minimum, 'minimum price');
            $maximum = market_price_import_decimal((string) $maximum, 'maximum price');
            $average = market_price_import_decimal((string) $average, 'average price');
            if ($minimum > $maximum || $average < $minimum || $average > $maximum) {
                throw new RuntimeException('Minimum ≤ average ≤ maximum must hold for every row.');
            }
            $category = fetch_one('SELECT id, name FROM produce_categories WHERE LOWER(name) = LOWER(:name) AND is_active = 1 LIMIT 1', ['name' => $product]);
            $location = fetch_one('SELECT id, district FROM locations WHERE LOWER(district) = LOWER(:district) ORDER BY id ASC LIMIT 1', ['district' => $district]);
            if ($category === null) {
                throw new RuntimeException('Product is not an active local produce category.');
            }
            if ($location === null) {
                throw new RuntimeException('District is not in the local reference locations.');
            }
            $rows[] = [
                'category_id' => (int) $category['id'],
                'location_id' => (int) $location['id'],
                'min_price' => $minimum,
                'max_price' => $maximum,
                'average_price' => $average,
                'unit' => $unit,
                'price_date' => market_price_import_date((string) $priceDate),
                'notes' => $notes,
            ];
        } catch (Throwable $exception) {
            $errors[] = 'Row ' . $rowNumber . ': ' . $exception->getMessage();
        }
        if (count($errors) >= 20) {
            break;
        }
        if (count($rows) > 500) {
            $errors[] = 'Use no more than 500 market-price rows in one import.';
            break;
        }
    }
    fclose($handle);
    if ($rows === [] && $errors === []) {
        $errors[] = 'The CSV file has no data rows.';
    }
    if ($errors !== []) {
        return [false, ['errors' => $errors, 'total_rows' => count($rows) + count($errors)]];
    }

    $filename = basename((string) ($file['name'] ?? 'market-prices.csv'));
    $filename = market_price_import_text($filename, 190);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $batch = $pdo->prepare('INSERT INTO market_price_import_batches (imported_by_user_id, source_name, source_reference, original_filename, total_rows) VALUES (:administrator_id, :source_name, :source_reference, :filename, :total_rows)');
        $batch->execute(['administrator_id' => $administratorId, 'source_name' => $sourceName, 'source_reference' => $sourceReference !== '' ? $sourceReference : null, 'filename' => $filename, 'total_rows' => count($rows)]);
        $batchId = (int) $pdo->lastInsertId();
        $exists = $pdo->prepare('SELECT id FROM market_prices WHERE category_id = :category_id AND location_id = :location_id AND price_date = :price_date AND unit = :unit LIMIT 1');
        $upsert = $pdo->prepare('INSERT INTO market_prices (category_id, location_id, recorded_by_user_id, price_date, min_price, max_price, average_price, unit, notes, source_name, source_reference) VALUES (:category_id, :location_id, :administrator_id, :price_date, :min_price, :max_price, :average_price, :unit, :notes, :source_name, :source_reference) ON DUPLICATE KEY UPDATE recorded_by_user_id = VALUES(recorded_by_user_id), min_price = VALUES(min_price), max_price = VALUES(max_price), average_price = VALUES(average_price), notes = VALUES(notes), source_name = VALUES(source_name), source_reference = VALUES(source_reference), updated_at = CURRENT_TIMESTAMP');
        $inserted = 0;
        $updated = 0;
        foreach ($rows as $row) {
            $exists->execute(['category_id' => $row['category_id'], 'location_id' => $row['location_id'], 'price_date' => $row['price_date'], 'unit' => $row['unit']]);
            $isUpdate = $exists->fetch() !== false;
            $upsert->execute($row + ['administrator_id' => $administratorId, 'source_name' => $sourceName, 'source_reference' => $sourceReference !== '' ? $sourceReference : null]);
            $isUpdate ? $updated++ : $inserted++;
        }
        $pdo->prepare('UPDATE market_price_import_batches SET inserted_rows = :inserted_rows, updated_rows = :updated_rows WHERE id = :batch_id')->execute(['inserted_rows' => $inserted, 'updated_rows' => $updated, 'batch_id' => $batchId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Local market-data import failed: ' . $exception->getMessage());
        throw new RuntimeException('The market-data import could not be completed. No rows were saved.');
    }

    audit_log($administratorId, 'market_price_imported', 'market_price_import_batches', $batchId, ['source_name' => $sourceName, 'total_rows' => count($rows), 'inserted_rows' => $inserted, 'updated_rows' => $updated]);
    return [true, ['batch_id' => $batchId, 'total_rows' => count($rows), 'inserted_rows' => $inserted, 'updated_rows' => $updated]];
}

function market_price_import_history(): array
{
    if (!market_price_imports_are_available()) {
        return [];
    }
    return fetch_all('SELECT b.*, u.full_name AS importer_name FROM market_price_import_batches b JOIN users u ON u.id = b.imported_by_user_id ORDER BY b.created_at DESC, b.id DESC LIMIT 30');
}
