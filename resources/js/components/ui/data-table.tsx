import { tableFeatures, useTable, type ColumnDef } from "@tanstack/react-table";

const features = tableFeatures({});
export type DataColumn<T extends { id: number }> = ColumnDef<
    typeof features,
    T
>;

/** Rendering foundation only: filtering, ordering and pagination stay server-side. */
export function DataTable<T extends { id: number }>({
    data,
    columns,
    caption,
}: {
    data: T[];
    columns: DataColumn<T>[];
    caption: string;
}) {
    const table = useTable({
        features,
        data,
        columns,
        getRowId: (row) => String(row.id),
    });
    return (
        <div className="overflow-x-auto rounded-xl border bg-white shadow-sm">
            <table className="w-full min-w-[38rem] text-left text-sm">
                <caption className="sr-only">{caption}</caption>
                <thead className="border-b bg-slate-50 text-xs text-slate-500">
                    {table.getHeaderGroups().map((group) => (
                        <tr key={group.id}>
                            {group.headers.map((header) => (
                                <th
                                    key={header.id}
                                    scope="col"
                                    className="px-5 py-3"
                                >
                                    <table.FlexRender header={header} />
                                </th>
                            ))}
                        </tr>
                    ))}
                </thead>
                <tbody className="divide-y divide-slate-100">
                    {table.getRowModel().rows.map((row) => (
                        <tr key={row.id} className="hover:bg-slate-50/70">
                            {row.getAllCells().map((cell) => (
                                <td
                                    key={cell.id}
                                    className="px-5 py-4 align-top"
                                >
                                    <table.FlexRender cell={cell} />
                                </td>
                            ))}
                        </tr>
                    ))}
                    {!data.length && (
                        <tr>
                            <td
                                colSpan={columns.length}
                                className="p-10 text-center text-slate-500"
                            >
                                No matching records. Adjust your filters to try
                                again.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}
