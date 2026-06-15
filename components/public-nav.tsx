import Link from "next/link";
import { siteNav } from "@/lib/site-data";

export function PublicNav() {
  return (
    <header className="sticky top-0 z-30 border-b border-neutral-200 bg-white/95 backdrop-blur">
      <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <Link href="/" className="text-sm font-semibold uppercase tracking-[0.18em] text-[color:var(--brand-maroon)]">
          Zek Catering
        </Link>
        <nav className="hidden items-center gap-6 text-sm text-neutral-600 md:flex">
          {siteNav.map((item) => (
            <Link key={item.href} href={item.href} className="hover:text-neutral-950">
              {item.label}
            </Link>
          ))}
        </nav>
        <Link
          href="/inquire"
          className="inline-flex h-10 items-center justify-center rounded-md bg-[color:var(--brand-maroon)] px-4 text-sm font-medium text-white hover:bg-[color:var(--brand-brown)]"
        >
          Request quote
        </Link>
      </div>
      <nav className="flex gap-4 overflow-x-auto border-t border-neutral-200 px-4 py-3 text-sm text-neutral-600 md:hidden">
        {siteNav.map((item) => (
          <Link key={item.href} href={item.href} className="shrink-0 hover:text-neutral-950">
            {item.label}
          </Link>
        ))}
      </nav>
    </header>
  );
}

export function PublicFooter() {
  return (
    <footer className="border-t border-[color:var(--brand-line)] bg-neutral-950 text-neutral-300">
      <div className="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:px-6 md:grid-cols-3 lg:px-8">
        <div>
          <p className="text-sm font-semibold uppercase tracking-[0.18em] text-white">Zek Catering</p>
          <p className="mt-4 max-w-sm text-sm leading-6">
            Event catering for weddings, private celebrations, and polished business gatherings.
          </p>
        </div>
        <div className="text-sm">
          <p className="font-medium text-white">Studio</p>
          <p className="mt-4">Aparente Street, Brgy. City Heights, General Santos City</p>
          <p className="mt-2">0919 525 5555 / 0907 253 0471</p>
        </div>
        <div className="text-sm">
          <p className="font-medium text-white">Preview</p>
          <Link href="/admin/dashboard" className="mt-4 inline-block hover:text-white">
            Admin workspace
          </Link>
        </div>
      </div>
    </footer>
  );
}
