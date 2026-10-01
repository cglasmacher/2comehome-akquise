export type User = {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'agent';
    active: boolean;
};
export type Shared = {
    auth: { user: User };
    flash: { success?: string; error?: string };
    statuses: Record<string, string>;
    propertyTypes: Record<string, string>;
    [key: string]: unknown;
};
export type Listing = {
    id: number;
    source: string;
    url: string | null;
    contact_url: string | null;
    availability: string;
    first_seen_at: string;
    last_seen_at: string;
    price_history: { price: number; at: string }[];
};
export type Property = {
    id: number;
    title: string;
    property_type: string;
    market: 'sale' | 'rent';
    postal_code: string | null;
    city: string | null;
    street: string | null;
    price: string | null;
    area: string | null;
    rooms: string | null;
    description: string | null;
    location_approximate: boolean;
    onoffice_id: number | null;
    listings: Listing[];
};
export type Contact = {
    name: string | null;
    email: string | null;
    phone: string | null;
    provider_type: string;
    classification_reason: string;
    contact_blocked: boolean;
    block_reason: string | null;
    onoffice_id: number | null;
};
export type Activity = {
    id: number;
    type: string;
    note: string;
    occurred_at: string;
    user: User | null;
    onoffice_id: number | null;
};
export type Lead = {
    id: number;
    property: Property;
    contact: Contact;
    assignee: User | null;
    assigned_to: number | null;
    status: string;
    acquisition_active: boolean;
    follow_up_at: string | null;
    contact_permission: string | null;
    transfer_state: string;
    transfer_error: string | null;
    activities: Activity[];
};
export type Profile = {
    id: number;
    name: string;
    active: boolean;
    postal_patterns: string[];
    center: string | null;
    latitude: string | null;
    longitude: string | null;
    radius_km: string | null;
    area_mode: string;
    sources: string[];
    property_types: string[];
    markets: string[];
    min_price: string | null;
    max_price: string | null;
    min_area: string | null;
    max_area: string | null;
};
export const money = (v: string | number | null) =>
    v === null
        ? 'Preis nicht angegeben'
        : new Intl.NumberFormat('de-DE', {
              style: 'currency',
              currency: 'EUR',
              maximumFractionDigits: 0,
          }).format(Number(v));
export const date = (v: string | null) =>
    v ? new Date(v).toLocaleString('de-DE', { dateStyle: 'short', timeStyle: 'short' }) : '—';
export const localInput = (v?: string | null) => {
    const d = v ? new Date(v) : new Date();
    return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
};
export const sources: Record<string, string> = {
    immowelt: 'Immowelt',
    immoscout24: 'ImmoScout24',
    kleinanzeigen: 'Kleinanzeigen',
    manual: 'Manuell',
};
export const provider: Record<string, string> = {
    private: 'Privat',
    commercial: 'Gewerblich / Makler',
    unclear: 'Prüfung offen',
};
