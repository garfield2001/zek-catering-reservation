export type PaymentListItem = {
    id: number;
    quotation_id: number | null;
    status: string;
    amount_minor: string | null;
    paid_at: string | null;
    method: { name: string } | null;
    quotation: { reference: string } | null;
    reservation_request: { reference: string } | null;
    transaction_reference: string | null;
    has_proof: boolean;
    evidence_versions: Array<{
        id: number;
        version_number: number;
        submitted_at: string;
    }>;
    allocation_history: Array<{
        quotation_version: { version_number: number };
        superseded_at: string | null;
    }>;
};

export type PaymentMethod = {
    id: number;
    name: string;
    is_offline: boolean;
};

export type AcceptedQuotationVersion = {
    id: number;
    quotation_id: number;
    version_number: number;
    quotation: { reference: string };
};
