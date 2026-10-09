export type SharedProps = {
    auth: {
        user: { id: number; name: string; email: string };
        isAdmin: boolean;
        permissions: string[];
    } | null;
    flash: { status?: string };
    [key: string]: unknown;
};
export type Pagination<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
