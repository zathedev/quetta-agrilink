/** Managed-preview operational store: mirrors account-scoped records in browser storage without replacing the production server as the source of truth. */
export type AccountRole = "farmer" | "buyer" | "storage" | "transport" | "admin";
export type OperationKind = "offer" | "storage" | "transport";

export type SessionRecord = { role: AccountRole; email: string; startedAt: string };
export type OperationRecord = { id: string; kind: OperationKind; title: string; detail: string; status: string; createdAt: string; ownerRole: AccountRole };
export type AdminRecord = { id: string; register: "listings" | "storage" | "fleet" | "prices"; values: string[]; createdAt: string; attachmentName?: string };

const SESSION_KEY = "qli-session-v1";
const OPERATIONS_KEY = "qli-operations-v1";
const ADMIN_RECORDS_KEY = "qli-admin-records-v1";

function read<T>(key: string): T[] {
  if (typeof window === "undefined") return [];
  try { return JSON.parse(window.localStorage.getItem(key) ?? "[]") as T[]; } catch { return []; }
}
function write<T>(key: string, value: T[]) { if (typeof window !== "undefined") window.localStorage.setItem(key, JSON.stringify(value)); }
function id(prefix: string) { return `${prefix}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 6)}`; }

export function getSession(): SessionRecord | null {
  if (typeof window === "undefined") return null;
  try { return JSON.parse(window.localStorage.getItem(SESSION_KEY) ?? "null") as SessionRecord | null; } catch { return null; }
}
export function startSession(role: AccountRole, email: string) {
  const session = { role, email, startedAt: new Date().toISOString() };
  if (typeof window !== "undefined") window.localStorage.setItem(SESSION_KEY, JSON.stringify(session));
  return session;
}
export function clearSession() { if (typeof window !== "undefined") window.localStorage.removeItem(SESSION_KEY); }
export function addOperation(input: Omit<OperationRecord, "id" | "createdAt">) {
  const record = { ...input, id: id("QAL"), createdAt: new Date().toISOString() };
  write(OPERATIONS_KEY, [record, ...read<OperationRecord>(OPERATIONS_KEY)]);
  return record;
}
export function getOperations(role?: AccountRole) { const items = read<OperationRecord>(OPERATIONS_KEY); return role ? items.filter((item) => item.ownerRole === role) : items; }
export function addAdminRecord(register: AdminRecord["register"], values: string[]) {
  const record = { id: id("QAR"), register, values, createdAt: new Date().toISOString() };
  write(ADMIN_RECORDS_KEY, [record, ...read<AdminRecord>(ADMIN_RECORDS_KEY)]);
  return record;
}
export function getAdminRecords(register: AdminRecord["register"]) { return read<AdminRecord>(ADMIN_RECORDS_KEY).filter((record) => record.register === register); }
export function saveAdminRecord(record: AdminRecord) {
  const records = read<AdminRecord>(ADMIN_RECORDS_KEY);
  const index = records.findIndex((item) => item.id === record.id);
  if (index === -1) records.unshift(record); else records[index] = record;
  write(ADMIN_RECORDS_KEY, records);
  return record;
}
