/** PHP/XAMPP action contract used by the managed interface to keep field names, roles, and request states aligned. */
import type { AccountRole, OperationKind } from "@/lib/operations";

export type WorkflowContract = { endpoint: string; role: AccountRole; fields: readonly string[]; successStatus: string; notification: string };

export const workflowContract: Record<OperationKind, WorkflowContract> = {
  offer: { endpoint: "ajax/offers/create.php", role: "buyer", fields: ["listing_id", "quantity", "offered_price", "message"], successStatus: "Sent to farmer", notification: "New buyer offer" },
  storage: { endpoint: "ajax/storage/book.php", role: "farmer", fields: ["facility_id", "listing_id", "category_id", "quantity_kg", "start_date", "end_date"], successStatus: "Requested", notification: "New storage booking request" },
  transport: { endpoint: "ajax/transport/request.php", role: "farmer", fields: ["provider_id", "listing_id", "pickup_location_id", "delivery_location_id", "produce_description", "quantity_kg", "requires_refrigeration", "pickup_date"], successStatus: "Requested", notification: "New transport request" },
};

export function contractFor(kind: OperationKind) { return workflowContract[kind]; }
