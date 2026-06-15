"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  CalendarDays,
  ClipboardList,
  CreditCard,
  FileText,
  LayoutDashboard,
  Package,
  MessageSquareText,
  BarChart3,
  Settings,
  Soup,
  Users,
  UserCog,
} from "lucide-react";
import type { AppRole } from "@/lib/auth";

const navGroups = [
  {
    label: "Daily work",
    items: [
      { label: "Dashboard", href: "/admin/dashboard", icon: LayoutDashboard, roles: ["admin", "staff"] },
      { label: "Inquiries", href: "/admin/inquiries", icon: ClipboardList, roles: ["admin", "staff"] },
      { label: "Quotations", href: "/admin/quotations", icon: FileText, roles: ["admin", "staff"] },
      { label: "Reservations", href: "/admin/reservations", icon: CalendarDays, roles: ["admin", "staff"] },
      { label: "Calendar", href: "/admin/calendar", icon: CalendarDays, roles: ["admin", "staff"] },
      { label: "Tasks", href: "/admin/tasks", icon: ClipboardList, roles: ["admin", "staff"] },
    ],
  },
  {
    label: "Catalog",
    items: [
      { label: "Packages", href: "/admin/catalog/packages", icon: Package, roles: ["admin", "staff"] },
      { label: "Menu", href: "/admin/catalog/dishes", icon: Soup, roles: ["admin", "staff"] },
    ],
  },
  {
    label: "Records",
    items: [
      { label: "Customers", href: "/admin/customers", icon: Users, roles: ["admin", "staff"] },
      { label: "Payments", href: "/admin/payments", icon: CreditCard, roles: ["admin", "staff"] },
      { label: "Feedback", href: "/admin/feedback", icon: MessageSquareText, roles: ["admin", "staff"] },
      { label: "Reports", href: "/admin/reports", icon: BarChart3, roles: ["admin", "staff"] },
    ],
  },
  {
    label: "System",
    items: [
      { label: "Staff", href: "/admin/staff", icon: UserCog, roles: ["admin", "staff"] },
      { label: "Settings", href: "/admin/settings", icon: Settings, roles: ["admin", "staff"] },
    ],
  },
];

export function AdminShell({
  children,
  profile,
}: {
  children: React.ReactNode;
  profile: { full_name: string; role: AppRole };
}) {
  const pathname = usePathname();
  const visibleNavGroups = navGroups
    .map((group) => ({ ...group, items: group.items.filter((item) => item.roles.includes(profile.role)) }))
    .filter((group) => group.items.length > 0);
  const visibleNavItems = visibleNavGroups.flatMap((group) => group.items);
  const currentPage = visibleNavItems.find((item) => pathname === item.href || pathname.startsWith(item.href + "/"));

  return (
    <div className="min-h-screen bg-neutral-100 text-neutral-950">
      <aside className="fixed inset-y-0 left-0 hidden h-screen w-72 flex-col border-r border-neutral-200 bg-white lg:flex">
        <div className="flex h-16 shrink-0 items-center border-b border-[color:var(--brand-line)] px-6">
          <Link href="/admin/dashboard" className="text-sm font-semibold uppercase tracking-[0.18em] text-[color:var(--brand-maroon)]">
            Zek Admin
          </Link>
        </div>
        <div className="shrink-0 border-b border-neutral-200 px-6 py-4">
          <p className="text-xs font-medium uppercase tracking-[0.16em] text-neutral-500">Staff flow</p>
          <p className="mt-2 text-sm leading-5 text-neutral-700">Inquiry to quotation to fee verification to reservation.</p>
        </div>
        <nav className="min-h-0 flex-1 overflow-y-auto px-3 py-4">
          {visibleNavGroups.map((group) => (
            <div key={group.label} className="mb-5 last:mb-0">
              <p className="mb-2 px-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-neutral-400">{group.label}</p>
              <div className="grid gap-1">
                {group.items.map((item) => {
                  const active = pathname === item.href || pathname.startsWith(item.href + "/");
                  const Icon = item.icon;

                  return (
                    <Link key={item.href} href={item.href} className={navItemClass(active)}>
                      <Icon className="h-4 w-4" />
                      {item.label}
                    </Link>
                  );
                })}
              </div>
            </div>
          ))}
        </nav>
        <div className="shrink-0 border-t border-neutral-200 px-4 py-3 text-xs text-neutral-500">
          Signed in as <span className="font-medium text-neutral-800">{profile.full_name}</span>
        </div>
      </aside>

      <div className="lg:pl-72">
        <header className="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-neutral-200 bg-white px-4 sm:px-6 lg:px-8">
          <div>
            <p className="text-xs font-medium uppercase tracking-[0.18em] text-neutral-500">Operations</p>
            <p className="text-sm font-semibold text-neutral-950">
              Dashboard / {currentPage?.label ?? "Admin workspace"}
            </p>
          </div>
          <div className="flex items-center gap-3 text-sm">
            <form action="/auth/signout" method="post">
              <button className="h-9 rounded-md border border-neutral-200 px-3 text-neutral-700 hover:bg-neutral-100">
                Sign out
              </button>
            </form>
            <div className="hidden h-9 rounded-md border border-neutral-200 px-3 py-2 text-neutral-700 sm:block">
              {profile.role}
            </div>
          </div>
        </header>
        <nav className="flex gap-2 overflow-x-auto border-b border-neutral-200 bg-white px-4 py-3 lg:hidden">
          {visibleNavItems.map((item) => {
            const active = pathname === item.href || pathname.startsWith(item.href + "/");

            return (
              <Link key={item.href} href={item.href} className={mobileNavItemClass(active)}>
                {item.label}
              </Link>
            );
          })}
        </nav>
        <main className="px-4 py-6 sm:px-6 lg:px-8">{children}</main>
      </div>
    </div>
  );
}

function navItemClass(active: boolean) {
  return [
    "flex h-10 items-center gap-3 rounded-md px-3 text-sm font-medium",
    active ? "bg-neutral-950 text-white" : "text-neutral-600 hover:bg-neutral-100 hover:text-neutral-950",
  ].join(" ");
}

function mobileNavItemClass(active: boolean) {
  return [
    "shrink-0 rounded-md px-3 py-2 text-sm font-medium",
    active ? "bg-neutral-950 text-white" : "text-neutral-600 hover:bg-neutral-100",
  ].join(" ");
}
