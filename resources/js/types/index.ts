export type SharedProps = {
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
            avatar_url?: string | null;
        };
        isAdmin: boolean;
        permissions: string[];
    } | null;
    flash: {
        status?: string;
        id?: string;
        customer_access_code?: string;
        quotation_test_otp?: string;
    };
    [key: string]: unknown;
};
export type Pagination<T> = {
    data: T[];
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
