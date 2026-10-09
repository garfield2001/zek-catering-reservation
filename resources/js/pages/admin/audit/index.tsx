import AdminLayout from "../../../layouts/admin-layout";
import {
    AuditList,
    type AuditEvent,
} from "../../../features/audit/components/audit-list";
import { PaginationControls } from "../../../components/ui/pagination";
import type { Pagination } from "../../../types";
export default function AuditIndex({
    events,
}: {
    events: Pagination<AuditEvent>;
}) {
    return (
        <AdminLayout
            title="Audit history"
            description="An append-only record of account activity and administrative changes. Times shown in Asia/Manila."
        >
            <AuditList events={events.data} />
            <PaginationControls {...events} />
        </AdminLayout>
    );
}
