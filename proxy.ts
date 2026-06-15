import { createServerClient } from "@supabase/ssr";
import { NextResponse, type NextRequest } from "next/server";

const adminHosts = new Set(["admin.zekcatering.com", "admin.localhost"]);
const publicHosts = new Set(["zekcatering.com", "www.zekcatering.com", "localhost", "127.0.0.1"]);

export async function proxy(request: NextRequest) {
  const hostname = (request.headers.get("host") ?? "").split(":")[0].toLowerCase();
  const path = request.nextUrl.pathname;
  const isAdminHost = adminHosts.has(hostname);
  const isKnownPublicHost = publicHosts.has(hostname);

  if (!isAdminHost && (path === "/login" || path.startsWith("/admin"))) {
    const url = request.nextUrl.clone();
    url.pathname = path === "/login" ? "/_not-found" : "/";
    url.search = "";
    return path === "/login" ? NextResponse.rewrite(url) : NextResponse.redirect(url);
  }

  if (!isAdminHost && isKnownPublicHost) {
    return NextResponse.next({ request });
  }

  const effectivePath = getAdminPath(path, isAdminHost);
  const checksAdminSession = isAdminHost || effectivePath.startsWith("/admin");
  if (!checksAdminSession) return NextResponse.next({ request });

  let response = NextResponse.next({ request });
  const supabase = createServerClient(
    process.env.NEXT_PUBLIC_SUPABASE_URL!,
    process.env.NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY!,
    {
      cookies: {
        getAll() {
          return request.cookies.getAll();
        },
        setAll(cookiesToSet) {
          cookiesToSet.forEach(({ name, value }) => request.cookies.set(name, value));
          response = NextResponse.next({ request });
          cookiesToSet.forEach(({ name, value, options }) => response.cookies.set(name, value, options));
        },
      },
    },
  );

  const {
    data: { user },
  } = await supabase.auth.getUser();

  if (effectivePath === "/admin/login" && user) {
    const url = request.nextUrl.clone();
    url.pathname = "/admin/dashboard";
    url.search = "";
    return withSessionCookies(NextResponse.redirect(url), response);
  }

  if (effectivePath.startsWith("/admin") && effectivePath !== "/admin/login" && !user) {
    const url = request.nextUrl.clone();
    url.pathname = "/admin/login";
    url.searchParams.set("next", effectivePath);
    return withSessionCookies(NextResponse.redirect(url), response);
  }

  if (effectivePath !== path) {
    const url = request.nextUrl.clone();
    url.pathname = effectivePath;
    return withSessionCookies(NextResponse.rewrite(url), response);
  }

  return response;
}

function getAdminPath(path: string, isAdminHost: boolean) {
  if (!isAdminHost) return path;
  if (path === "/") return "/admin/dashboard";
  if (path === "/login") return "/admin/login";
  if (path.startsWith("/admin") || path.startsWith("/auth")) return path;
  return `/admin${path}`;
}

function withSessionCookies(target: NextResponse, source: NextResponse) {
  source.cookies.getAll().forEach((cookie) => target.cookies.set(cookie));
  return target;
}

export const config = {
  matcher: ["/((?!_next/static|_next/image|favicon.ico|.*\\..*).*)"],
};
