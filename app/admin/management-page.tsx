type Metric = {
  label: string;
  value: string;
};

type Row = Record<string, string | number>;

export function ManagementPage({
  eyebrow,
  title,
  description,
  metrics,
  columns,
  rows,
}: {
  eyebrow: string;
  title: string;
  description: string;
  metrics: Metric[];
  columns: string[];
  rows: Row[];
}) {
  return (
    <div className="space-y-6">
      <section className="rounded-md border border-neutral-200 bg-white p-6">
        <p className="text-xs font-medium uppercase tracking-[0.18em] text-neutral-500">{eyebrow}</p>
        <div className="mt-3 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
          <div>
            <h1 className="text-3xl font-semibold tracking-tight text-neutral-950">{title}</h1>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-neutral-600">{description}</p>
          </div>
          <button className="h-10 rounded-md bg-neutral-950 px-4 text-sm font-medium text-white hover:bg-neutral-800">
            New record
          </button>
        </div>
      </section>

      <section className="grid gap-4 md:grid-cols-3">
        {metrics.map((metric) => (
          <div key={metric.label} className="rounded-md border border-neutral-200 bg-white p-5">
            <p className="text-sm text-neutral-500">{metric.label}</p>
            <p className="mt-2 text-2xl font-semibold text-neutral-950">{metric.value}</p>
          </div>
        ))}
      </section>

      <section className="overflow-hidden rounded-md border border-neutral-200 bg-white">
        <div className="flex flex-col justify-between gap-3 border-b border-neutral-200 p-4 sm:flex-row sm:items-center">
          <input
            placeholder="Search"
            className="h-10 w-full rounded-md border border-neutral-300 px-3 text-sm outline-none focus:border-neutral-950 sm:max-w-xs"
          />
          <select className="h-10 rounded-md border border-neutral-300 px-3 text-sm outline-none focus:border-neutral-950">
            <option>All statuses</option>
            <option>Active</option>
            <option>Pending</option>
            <option>Archived</option>
          </select>
        </div>
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-neutral-200 text-sm">
            <thead className="bg-neutral-50 text-left text-xs uppercase tracking-[0.12em] text-neutral-500">
              <tr>
                {columns.map((column) => (
                  <th key={column} className="px-4 py-3 font-medium">
                    {column}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-200">
              {rows.map((row, index) => (
                <tr key={index} className="hover:bg-neutral-50">
                  {columns.map((column) => (
                    <td key={column} className="whitespace-nowrap px-4 py-4 text-neutral-700">
                      {row[column]}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
}
