import { Link } from "@inertiajs/react";
export function PaginationControls({
    current_page,
    last_page,
    prev_page_url,
    next_page_url,
}: {
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}) {
    return (
        <nav
            aria-label="Pagination"
            className="flex items-center gap-5 py-5 text-sm"
        >
            {prev_page_url && (
                <Link className="underline" href={prev_page_url}>
                    Previous
                </Link>
            )}
            <span>
                Page {current_page} of {last_page}
            </span>
            {next_page_url && (
                <Link className="underline" href={next_page_url}>
                    Next
                </Link>
            )}
        </nav>
    );
}
