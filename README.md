# Zek Catering Reservation

Next.js app for a catering public site and admin operations workspace.

## Local Preview

Install dependencies and run the app:

```bash
pnpm install
pnpm run dev
```

Open:

- Public site: http://localhost:3000
- Admin workspace: http://admin.localhost:3000
- Admin login: http://admin.localhost:3000/admin/login

The public host does not expose `/login` or `/admin`. Staff should use an admin host only.

Optional local domain preview:

1. Open Notepad as Administrator.
2. Edit `C:\Windows\System32\drivers\etc\hosts`.
3. Add:

```txt
127.0.0.1 zekcatering.com
127.0.0.1 admin.zekcatering.com
```

4. Run `pnpm run dev`.
5. Open `http://zekcatering.com:3000` and `http://admin.zekcatering.com:3000`.

The Next proxy maps `admin.zekcatering.com` and `admin.localhost` into the admin app.

## Supabase Environments

Use two separate Supabase projects:

- `zek-catering-dev`: local development and testing
- `zek-catering-production`: real customer and admin data

Do not share database passwords, service role keys, buckets, or migration experiments across these projects.

For local development:

```bash
copy .env.development.example .env.local
```

Fill `.env.local` with the dev Supabase project values only.

Development catalog seeding is optional and should only be used against the dev Supabase project:

```bash
pnpm run seed:dev
```

The seed uses `supabase/seed.dev.sql`. It adds sample packages, menus, inclusions, food packs, food trays, lechon options, and settings. It does not create production data and does not create auth users.

For production, put production values in the deployment platform secret manager. Do not put production credentials in `.env.local`.

## Supabase Workflow

The repo is the source of truth for schema. Tables, constraints, indexes, RLS policies, helper functions, and storage buckets belong in `supabase/migrations`.

Development flow:

1. Make schema changes in a Supabase migration.
2. Reset/apply the dev database.

```bash
supabase db reset --linked
```

3. Run dev seed only if you want sample data.

```bash
pnpm run seed:dev
```

4. Test public inquiry, tracking, admin login, quotation, payment verification, and reservation flows against dev.
5. Promote the same migrations to production only after dev is confirmed.

Production flow:

1. Create a fresh production Supabase project.
2. Set production environment variables in the deployment platform.
3. Apply migrations only.
4. Manually create owner/admin and staff users in Supabase Auth.
5. Add catalog/settings through the admin UI.

Recommended rules:

- Keep production Supabase access limited to the account owner.
- Use publishable keys in browser code only.
- Never expose service role keys to the frontend.
- Keep storage buckets and RLS policies duplicated between dev and production.
- Keep sample data out of production.

## Admin Auth

Admin and staff login uses Supabase Auth email/password only. There is no public admin signup.

Create your first local test owner/admin:

1. In Supabase Dashboard, open Authentication > Users.
2. Add your email and a temporary password.
3. Copy the new auth user ID.
4. Insert a matching profile:

```sql
insert into public.app_profiles (auth_user_id, email, full_name, role)
values ('AUTH_USER_ID_HERE', 'your-email@example.com', 'Your Name', 'admin');
```

The `admin` role is owner/management. The `staff` role is for company staff.

Forgot password uses Supabase Auth email templates. Configure redirect URLs in Supabase Auth:

- Local: `http://admin.localhost:3000/auth/confirm`
- Production later: `https://admin.yourdomain.com/auth/confirm`

Customer SMS is separate from Supabase Auth and should use a provider such as Twilio when enabled.

## Reservation Model

The app separates the catering workflow into three related ideas:

- Inquiry: a customer asks for a quote or initial reservation. It is not a confirmed booking.
- Reservation: admin or staff creates the official booking after confirming the customer, date, venue, package, headcount, price, deposit, and notes.
- Event: the operational execution of a reservation on the scheduled date.

Typical flow:

1. Customer submits `/reserve`.
2. The app creates an `inquiries` row, logs an admin/staff system notification, and records a customer acknowledgement email.
3. Admin/staff opens the admin workspace.
4. Staff contacts the customer and creates the quotation or reservation record.
5. Staff finalizes booking details and payment instructions.
6. The date is reserved only after the reservation fee is verified.

Without `RESEND_API_KEY`, email is development-only and recorded in `notification_logs` as `dev-email-log`. Add `RESEND_API_KEY` and `RESERVATION_EMAIL_FROM` to send real email from the server.

## Current App Surfaces

Public pages:

- `/`
- `/services`
- `/packages`
- `/gallery`
- `/about`
- `/contact`
- `/reserve`
- `/status` redirects to `/track`
- `/track`

Admin pages:

- `/admin/dashboard`
- `/admin/catalog/packages`
- `/admin/catalog/dishes`
- `/admin/catalog/drinks`
- `/admin/catalog/addons`
- `/admin/inquiries`
- `/admin/quotations`
- `/admin/reservations`
- `/admin/calendar`
- `/admin/tasks`
- `/admin/payments`
- `/admin/feedback`
- `/admin/reports`
- `/admin/staff`
- `/admin/settings`

## Verification

```bash
pnpm run lint
pnpm audit --audit-level moderate
pnpm run build
```

Landing images are saved locally in `public/images/landing`. Package flyer images are saved locally in `public/images/packages`.
