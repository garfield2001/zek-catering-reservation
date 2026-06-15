create extension if not exists pgcrypto with schema public;

create schema if not exists private;

create type public.app_role as enum ('admin', 'staff');
create type public.catalog_status as enum ('available', 'unavailable', 'seasonal');
create type public.pricing_mode as enum ('none', 'flat', 'per_pax', 'per_quantity', 'per_pack', 'manual');
create type public.dish_type as enum ('standard', 'premium');

create sequence if not exists public.inquiry_reference_seq;
create sequence if not exists public.quotation_number_seq;
create sequence if not exists public.reservation_number_seq;

create or replace function public.generate_reference_code()
returns text
language sql
security definer
set search_path = public
as $$
  select 'REF-' || to_char(now(), 'YYYY') || '-' || lpad(nextval('public.inquiry_reference_seq')::text, 6, '0');
$$;

create or replace function public.generate_quotation_number()
returns text
language sql
security definer
set search_path = public
as $$
  select 'QT-' || to_char(now(), 'YYYY') || '-' || lpad(nextval('public.quotation_number_seq')::text, 6, '0');
$$;

create or replace function public.generate_reservation_code()
returns text
language sql
security definer
set search_path = public
as $$
  select 'RSV-' || to_char(now(), 'YYYY') || '-' || lpad(nextval('public.reservation_number_seq')::text, 6, '0');
$$;

create or replace function public.set_updated_at()
returns trigger
language plpgsql
as $$
begin
  new.updated_at = now();
  return new;
end;
$$;

create table public.app_profiles (
  id uuid primary key default gen_random_uuid(),
  auth_user_id uuid not null unique references auth.users(id) on delete cascade,
  email text not null unique,
  full_name text not null,
  role public.app_role not null default 'staff',
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint app_profiles_email_lowercase check (email = lower(email))
);

create table public.business_settings (
  id uuid primary key default gen_random_uuid(),
  business_name text not null default 'Zek Catering',
  business_phone text,
  business_email text,
  business_address text,
  minimum_booking_days integer not null default 14,
  customer_edit_window_hours integer not null default 24,
  quotation_validity_days integer not null default 3,
  reservation_fee_type text not null default 'fixed' check (reservation_fee_type in ('fixed', 'percentage')),
  reservation_fee_value numeric(12, 2) not null default 5000,
  reservation_fee_due_days integer not null default 3,
  final_payment_due_days integer not null default 7,
  pax_increase_deadline_days integer not null default 7,
  reschedule_deadline_days integer not null default 14,
  emergency_booking_fee numeric(12, 2) not null default 0,
  default_city text default 'General Santos City',
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.customers (
  id uuid primary key default gen_random_uuid(),
  full_name text not null,
  contact_number text not null,
  email text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.catering_packages (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  slug text not null unique,
  description text,
  price_per_pax numeric(12, 2) not null default 0,
  base_price numeric(12, 2) not null default 0,
  minimum_pax integer not null default 50,
  minimum_guests integer not null default 50,
  pax_increment integer not null default 50,
  meal_slots integer not null default 4,
  drink_slots integer not null default 1,
  rice_included boolean not null default true,
  service_style text not null default 'buffet',
  image_url text,
  sort_order integer not null default 0,
  is_active boolean not null default true,
  archived_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint catering_packages_pax_positive check (minimum_pax > 0 and pax_increment > 0),
  constraint catering_packages_slots_nonnegative check (meal_slots >= 0 and drink_slots >= 0)
);

create table public.package_inclusions (
  id uuid primary key default gen_random_uuid(),
  package_id uuid not null references public.catering_packages(id) on delete cascade,
  label text not null,
  sort_order integer not null default 0,
  created_at timestamptz not null default now()
);

create table public.dish_categories (
  id uuid primary key default gen_random_uuid(),
  name text not null unique,
  sort_order integer not null default 0,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.dishes (
  id uuid primary key default gen_random_uuid(),
  category_id uuid references public.dish_categories(id) on delete set null,
  name text not null,
  description text,
  image_url text,
  status public.catalog_status not null default 'available',
  dish_type public.dish_type not null default 'standard',
  premium_pricing_mode public.pricing_mode not null default 'none',
  premium_price numeric(12, 2) not null default 0,
  is_halal_friendly boolean not null default false,
  is_vegetarian boolean not null default false,
  contains_pork boolean not null default false,
  contains_common_allergens text[] not null default '{}',
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (category_id, name)
);

create table public.drinks (
  id uuid primary key default gen_random_uuid(),
  name text not null unique,
  description text,
  image_url text,
  status public.catalog_status not null default 'available',
  is_premium boolean not null default false,
  premium_pricing_mode public.pricing_mode not null default 'none',
  premium_price numeric(12, 2) not null default 0,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.addons (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  description text,
  category text,
  pricing_mode public.pricing_mode not null default 'flat',
  price numeric(12, 2) not null default 0,
  min_quantity integer not null default 1,
  input_type text not null default 'checkbox' check (input_type in ('checkbox', 'quantity', 'option', 'note')),
  unit_label text,
  image_url text,
  is_public boolean not null default true,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.food_trays (
  id uuid primary key default gen_random_uuid(),
  dish_id uuid references public.dishes(id) on delete set null,
  name text not null,
  category text,
  price_per_tray numeric(12, 2) not null default 0,
  good_for_min integer not null default 12,
  good_for_max integer not null default 15,
  description text,
  image_url text,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.food_packs (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  description text,
  price_per_pack numeric(12, 2) not null default 0,
  minimum_packs integer not null default 10,
  includes text[] not null default '{}',
  image_url text,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.lechon_options (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  weight_kg numeric(6, 2),
  price numeric(12, 2) not null default 0,
  description text,
  good_for_pax integer,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.inquiries (
  id uuid primary key default gen_random_uuid(),
  ref_code text not null unique default public.generate_reference_code(),
  reference_code text unique,
  customer_id uuid references public.customers(id) on delete set null,
  customer_name text not null,
  customer_email text,
  customer_phone text not null,
  preferred_contact_method text not null default 'phone',
  lead_source text not null default 'website',
  event_type text not null,
  event_date date not null,
  event_time time,
  event_end_time time,
  guest_count integer not null,
  estimated_pax integer,
  venue_name text,
  venue_address text,
  barangay text,
  city text,
  province text,
  package_id uuid references public.catering_packages(id) on delete set null,
  budget_min numeric(12, 2),
  budget_max numeric(12, 2),
  transportation_fee numeric(12, 2),
  transportation_fee_reason text,
  emergency_booking_fee numeric(12, 2),
  notes text,
  customer_notes text,
  internal_notes text,
  status text not null default 'new',
  customer_edit_until timestamptz,
  consultation_started_at timestamptz,
  consultation_locked_at timestamptz,
  last_contacted_at timestamptz,
  created_by uuid references public.app_profiles(id) on delete set null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint inquiries_guest_count_positive check (guest_count > 0)
);

create or replace function public.ensure_inquiry_reference_code()
returns trigger
language plpgsql
as $$
begin
  if new.ref_code is null or length(trim(new.ref_code)) = 0 then
    new.ref_code := public.generate_reference_code();
  end if;

  if new.reference_code is null or length(trim(new.reference_code)) = 0 then
    new.reference_code := new.ref_code;
  end if;

  new.ref_code := upper(new.ref_code);
  new.reference_code := upper(new.reference_code);
  return new;
end;
$$;

create table public.inquiry_menu_selections (
  id uuid primary key default gen_random_uuid(),
  inquiry_id uuid not null references public.inquiries(id) on delete cascade,
  item_type text not null check (item_type in ('dish', 'drink', 'addon', 'food_tray', 'food_pack', 'lechon')),
  item_id uuid,
  snapshot_name text not null,
  snapshot_price numeric(12, 2) not null default 0,
  quantity integer not null default 1,
  created_at timestamptz not null default now()
);

create table public.inquiry_special_requirements (
  id uuid primary key default gen_random_uuid(),
  inquiry_id uuid not null references public.inquiries(id) on delete cascade,
  requirement_type text not null,
  notes text,
  created_at timestamptz not null default now()
);

create table public.inquiry_notes (
  id uuid primary key default gen_random_uuid(),
  inquiry_id uuid not null references public.inquiries(id) on delete cascade,
  author_profile_id uuid references public.app_profiles(id) on delete set null,
  note text not null,
  is_customer_visible boolean not null default false,
  created_at timestamptz not null default now()
);

create table public.quotations (
  id uuid primary key default gen_random_uuid(),
  inquiry_id uuid not null references public.inquiries(id) on delete cascade,
  package_id uuid references public.catering_packages(id) on delete set null,
  quotation_number text not null unique default public.generate_quotation_number(),
  status text not null default 'draft',
  pax integer not null,
  package_price_per_pax numeric(12, 2) not null default 0,
  subtotal numeric(12, 2) not null default 0,
  transportation_fee numeric(12, 2) not null default 0,
  transportation_fee_reason text,
  premium_total numeric(12, 2) not null default 0,
  addon_total numeric(12, 2) not null default 0,
  special_charge_total numeric(12, 2) not null default 0,
  discount_total numeric(12, 2) not null default 0,
  total_amount numeric(12, 2) not null default 0,
  reservation_fee_type text not null default 'fixed' check (reservation_fee_type in ('fixed', 'percentage')),
  reservation_fee_value numeric(12, 2) not null default 0,
  reservation_fee_amount numeric(12, 2) not null default 0,
  reservation_fee_due_at timestamptz,
  final_payment_due_at timestamptz,
  valid_until timestamptz,
  customer_message text,
  created_by uuid references public.app_profiles(id) on delete set null,
  sent_at timestamptz,
  accepted_at timestamptz,
  expired_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint quotations_pax_positive check (pax > 0)
);

create table public.quotation_items (
  id uuid primary key default gen_random_uuid(),
  quotation_id uuid not null references public.quotations(id) on delete cascade,
  line_type text not null,
  description text not null,
  quantity numeric(12, 2) not null default 1,
  unit_price numeric(12, 2) not null default 0,
  amount numeric(12, 2) not null default 0,
  customer_visible_reason text,
  sort_order integer not null default 0,
  created_at timestamptz not null default now()
);

create table public.quotation_menu_selections (
  id uuid primary key default gen_random_uuid(),
  quotation_id uuid not null references public.quotations(id) on delete cascade,
  selection_type text not null,
  item_name_snapshot text not null,
  premium_amount_snapshot numeric(12, 2) not null default 0,
  created_at timestamptz not null default now()
);

create table public.reservations (
  id uuid primary key default gen_random_uuid(),
  inquiry_id uuid not null unique references public.inquiries(id) on delete restrict,
  quotation_id uuid references public.quotations(id) on delete set null,
  reservation_code text not null unique default public.generate_reservation_code(),
  customer_id uuid references public.customers(id) on delete set null,
  customer_name text not null,
  customer_phone text not null,
  customer_email text,
  event_type text not null,
  event_date date not null,
  event_time time,
  venue_name text,
  venue_address text,
  final_pax integer not null,
  status text not null default 'confirmed',
  total_amount numeric(12, 2) not null default 0,
  reservation_fee_amount numeric(12, 2) not null default 0,
  balance_amount numeric(12, 2) not null default 0,
  final_payment_due_at timestamptz,
  final_payment_status text not null default 'pending',
  confirmed_at timestamptz,
  completed_at timestamptz,
  cancelled_at timestamptz,
  cancellation_reason text,
  created_by uuid references public.app_profiles(id) on delete set null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint reservations_final_pax_positive check (final_pax > 0)
);

create table public.reservation_line_items (
  id uuid primary key default gen_random_uuid(),
  reservation_id uuid not null references public.reservations(id) on delete cascade,
  item_type text not null,
  item_name_snapshot text not null,
  quantity numeric(12, 2) not null default 1,
  amount numeric(12, 2) not null default 0,
  created_at timestamptz not null default now()
);

create table public.payments (
  id uuid primary key default gen_random_uuid(),
  inquiry_id uuid references public.inquiries(id) on delete cascade,
  quotation_id uuid references public.quotations(id) on delete set null,
  reservation_id uuid references public.reservations(id) on delete set null,
  amount numeric(12, 2) not null,
  payment_method text not null,
  payment_type text not null default 'reservation_fee',
  payment_status text not null default 'pending',
  reference_number text,
  proof_url text,
  uploaded_by_customer boolean not null default false,
  verified_by uuid references public.app_profiles(id) on delete set null,
  verified_at timestamptz,
  rejected_reason text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint payments_amount_positive check (amount > 0)
);

create table public.internal_tasks (
  id uuid primary key default gen_random_uuid(),
  inquiry_id uuid references public.inquiries(id) on delete cascade,
  reservation_id uuid references public.reservations(id) on delete cascade,
  assigned_to uuid references public.app_profiles(id) on delete set null,
  title text not null,
  description text,
  due_at timestamptz,
  status text not null default 'open',
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.feedback (
  id uuid primary key default gen_random_uuid(),
  reservation_id uuid references public.reservations(id) on delete cascade,
  inquiry_id uuid references public.inquiries(id) on delete cascade,
  customer_id uuid references public.customers(id) on delete set null,
  rating integer not null check (rating between 1 and 5),
  food_quality_rating integer check (food_quality_rating between 1 and 5),
  service_quality_rating integer check (service_quality_rating between 1 and 5),
  overall_experience_rating integer check (overall_experience_rating between 1 and 5),
  comments text,
  created_at timestamptz not null default now()
);

create table public.notifications (
  id uuid primary key default gen_random_uuid(),
  inquiry_id uuid references public.inquiries(id) on delete cascade,
  reservation_id uuid references public.reservations(id) on delete cascade,
  channel text not null check (channel in ('email', 'sms', 'system')),
  recipient text not null,
  template_key text,
  subject text,
  body text not null,
  status text not null default 'queued',
  provider text,
  provider_message_id text,
  error_message text,
  sent_at timestamptz,
  created_at timestamptz not null default now()
);

create table public.notification_logs (
  id uuid primary key default gen_random_uuid(),
  inquiry_id uuid references public.inquiries(id) on delete cascade,
  reservation_id uuid references public.reservations(id) on delete cascade,
  channel text not null,
  recipient text not null,
  subject text,
  body text not null,
  status text not null,
  provider_reference text,
  sent_at timestamptz,
  created_at timestamptz not null default now()
);

create table public.activity_logs (
  id uuid primary key default gen_random_uuid(),
  inquiry_id uuid references public.inquiries(id) on delete cascade,
  reservation_id uuid references public.reservations(id) on delete cascade,
  actor_profile_id uuid references public.app_profiles(id) on delete set null,
  action text not null,
  description text,
  old_values jsonb,
  new_values jsonb,
  created_at timestamptz not null default now()
);

create table public.audit_logs (
  id uuid primary key default gen_random_uuid(),
  actor_profile_id uuid references public.app_profiles(id) on delete set null,
  action text not null,
  table_name text,
  record_id uuid,
  old_values jsonb,
  new_values jsonb,
  reason text,
  created_at timestamptz not null default now()
);

create trigger set_app_profiles_updated_at before update on public.app_profiles for each row execute function public.set_updated_at();
create trigger set_business_settings_updated_at before update on public.business_settings for each row execute function public.set_updated_at();
create trigger set_customers_updated_at before update on public.customers for each row execute function public.set_updated_at();
create trigger set_catering_packages_updated_at before update on public.catering_packages for each row execute function public.set_updated_at();
create trigger set_dish_categories_updated_at before update on public.dish_categories for each row execute function public.set_updated_at();
create trigger set_dishes_updated_at before update on public.dishes for each row execute function public.set_updated_at();
create trigger set_drinks_updated_at before update on public.drinks for each row execute function public.set_updated_at();
create trigger set_addons_updated_at before update on public.addons for each row execute function public.set_updated_at();
create trigger set_food_trays_updated_at before update on public.food_trays for each row execute function public.set_updated_at();
create trigger set_food_packs_updated_at before update on public.food_packs for each row execute function public.set_updated_at();
create trigger set_lechon_options_updated_at before update on public.lechon_options for each row execute function public.set_updated_at();
create trigger set_inquiries_updated_at before update on public.inquiries for each row execute function public.set_updated_at();
create trigger ensure_inquiry_reference_code before insert on public.inquiries for each row execute function public.ensure_inquiry_reference_code();
create trigger set_quotations_updated_at before update on public.quotations for each row execute function public.set_updated_at();
create trigger set_reservations_updated_at before update on public.reservations for each row execute function public.set_updated_at();
create trigger set_payments_updated_at before update on public.payments for each row execute function public.set_updated_at();
create trigger set_internal_tasks_updated_at before update on public.internal_tasks for each row execute function public.set_updated_at();

create index idx_app_profiles_auth_user_id on public.app_profiles(auth_user_id);
create index idx_app_profiles_role_active on public.app_profiles(role, is_active);
create index idx_customers_contact_number on public.customers(contact_number);
create index idx_package_inclusions_package_id on public.package_inclusions(package_id);
create index idx_dishes_category_id on public.dishes(category_id);
create index idx_food_trays_dish_id on public.food_trays(dish_id);
create index idx_inquiries_customer_id on public.inquiries(customer_id);
create index idx_inquiries_package_id on public.inquiries(package_id);
create index idx_inquiries_created_by on public.inquiries(created_by);
create index idx_inquiries_ref_phone on public.inquiries(ref_code, customer_phone);
create index idx_inquiries_reference_phone on public.inquiries(reference_code, customer_phone);
create index idx_inquiries_event_date_status on public.inquiries(event_date, status);
create index idx_inquiry_menu_selections_inquiry_id on public.inquiry_menu_selections(inquiry_id);
create index idx_inquiry_special_requirements_inquiry_id on public.inquiry_special_requirements(inquiry_id);
create index idx_inquiry_notes_inquiry_id on public.inquiry_notes(inquiry_id);
create index idx_inquiry_notes_author_profile_id on public.inquiry_notes(author_profile_id);
create index idx_quotations_inquiry_id on public.quotations(inquiry_id);
create index idx_quotations_package_id on public.quotations(package_id);
create index idx_quotations_created_by on public.quotations(created_by);
create index idx_quotation_items_quotation_id on public.quotation_items(quotation_id);
create index idx_quotation_menu_selections_quotation_id on public.quotation_menu_selections(quotation_id);
create index idx_reservations_inquiry_id on public.reservations(inquiry_id);
create index idx_reservations_quotation_id on public.reservations(quotation_id);
create index idx_reservations_customer_id on public.reservations(customer_id);
create index idx_reservations_created_by on public.reservations(created_by);
create index idx_reservations_event_date_status on public.reservations(event_date, status);
create index idx_reservation_line_items_reservation_id on public.reservation_line_items(reservation_id);
create index idx_payments_inquiry_id on public.payments(inquiry_id);
create index idx_payments_quotation_id on public.payments(quotation_id);
create index idx_payments_reservation_id on public.payments(reservation_id);
create index idx_payments_verified_by on public.payments(verified_by);
create index idx_internal_tasks_inquiry_id on public.internal_tasks(inquiry_id);
create index idx_internal_tasks_reservation_id on public.internal_tasks(reservation_id);
create index idx_internal_tasks_assigned_to on public.internal_tasks(assigned_to);
create index idx_feedback_reservation_id on public.feedback(reservation_id);
create index idx_feedback_inquiry_id on public.feedback(inquiry_id);
create index idx_feedback_customer_id on public.feedback(customer_id);
create index idx_notifications_inquiry_id on public.notifications(inquiry_id);
create index idx_notifications_reservation_id on public.notifications(reservation_id);
create index idx_notification_logs_inquiry_id on public.notification_logs(inquiry_id);
create index idx_notification_logs_reservation_id on public.notification_logs(reservation_id);
create index idx_activity_logs_inquiry_id on public.activity_logs(inquiry_id);
create index idx_activity_logs_reservation_id on public.activity_logs(reservation_id);
create index idx_activity_logs_actor_profile_id on public.activity_logs(actor_profile_id);
create index idx_audit_logs_actor_profile_id on public.audit_logs(actor_profile_id);

create or replace function private.current_app_role()
returns public.app_role
language sql
security definer
set search_path = public, private
stable
as $$
  select role
  from public.app_profiles
  where auth_user_id = auth.uid()
    and is_active = true
  limit 1;
$$;

create or replace function private.is_staff_member()
returns boolean
language sql
security definer
set search_path = public, private
stable
as $$
  select coalesce(private.current_app_role() in ('admin', 'staff'), false);
$$;

create or replace function private.is_admin()
returns boolean
language sql
security definer
set search_path = public, private
stable
as $$
  select coalesce(private.current_app_role() = 'admin', false);
$$;

create or replace function public.can_customer_edit_inquiry(p_inquiry_id uuid)
returns boolean
language sql
security definer
set search_path = public
stable
as $$
  select exists (
    select 1
    from public.inquiries i
    where i.id = p_inquiry_id
      and i.consultation_locked_at is null
      and i.status in ('new', 'new_inquiry')
      and i.customer_edit_until > now()
  );
$$;

alter table public.app_profiles enable row level security;
alter table public.business_settings enable row level security;
alter table public.customers enable row level security;
alter table public.catering_packages enable row level security;
alter table public.package_inclusions enable row level security;
alter table public.dish_categories enable row level security;
alter table public.dishes enable row level security;
alter table public.drinks enable row level security;
alter table public.addons enable row level security;
alter table public.food_trays enable row level security;
alter table public.food_packs enable row level security;
alter table public.lechon_options enable row level security;
alter table public.inquiries enable row level security;
alter table public.inquiry_menu_selections enable row level security;
alter table public.inquiry_special_requirements enable row level security;
alter table public.inquiry_notes enable row level security;
alter table public.quotations enable row level security;
alter table public.quotation_items enable row level security;
alter table public.quotation_menu_selections enable row level security;
alter table public.reservations enable row level security;
alter table public.reservation_line_items enable row level security;
alter table public.payments enable row level security;
alter table public.internal_tasks enable row level security;
alter table public.feedback enable row level security;
alter table public.notifications enable row level security;
alter table public.notification_logs enable row level security;
alter table public.activity_logs enable row level security;
alter table public.audit_logs enable row level security;

create policy "active packages are public" on public.catering_packages for select to anon using (is_active = true and archived_at is null);
create policy "active package inclusions are public" on public.package_inclusions for select to anon using (
  exists (
    select 1 from public.catering_packages p
    where p.id = package_inclusions.package_id and p.is_active = true and p.archived_at is null
  )
);
create policy "active dish categories are public" on public.dish_categories for select to anon using (is_active = true);
create policy "available dishes are public" on public.dishes for select to anon using (is_active = true and status = 'available');
create policy "available drinks are public" on public.drinks for select to anon using (is_active = true and status = 'available');
create policy "public addons are public" on public.addons for select to anon using (is_active = true and is_public = true);
create policy "active food trays are public" on public.food_trays for select to anon using (is_active = true);
create policy "active food packs are public" on public.food_packs for select to anon using (is_active = true);
create policy "active lechon options are public" on public.lechon_options for select to anon using (is_active = true);

create policy "profiles can read self" on public.app_profiles for select to authenticated using (auth_user_id = auth.uid());
create policy "admins manage profiles" on public.app_profiles for all to authenticated using (private.is_admin()) with check (private.is_admin());
create policy "staff read business settings" on public.business_settings for select to authenticated using (private.is_staff_member());
create policy "admins manage business settings" on public.business_settings for all to authenticated using (private.is_admin()) with check (private.is_admin());

create policy "staff read customers" on public.customers for select to authenticated using (private.is_staff_member());
create policy "staff manage customers" on public.customers for all to authenticated using (private.is_staff_member()) with check (private.is_staff_member());

create policy "staff read packages" on public.catering_packages for select to authenticated using (private.is_staff_member());
create policy "admins manage packages" on public.catering_packages for all to authenticated using (private.is_admin()) with check (private.is_admin());
create policy "staff read package inclusions" on public.package_inclusions for select to authenticated using (private.is_staff_member());
create policy "admins manage package inclusions" on public.package_inclusions for all to authenticated using (private.is_admin()) with check (private.is_admin());
create policy "staff read dish categories" on public.dish_categories for select to authenticated using (private.is_staff_member());
create policy "admins manage dish categories" on public.dish_categories for all to authenticated using (private.is_admin()) with check (private.is_admin());
create policy "staff read dishes" on public.dishes for select to authenticated using (private.is_staff_member());
create policy "admins manage dishes" on public.dishes for all to authenticated using (private.is_admin()) with check (private.is_admin());
create policy "staff read drinks" on public.drinks for select to authenticated using (private.is_staff_member());
create policy "admins manage drinks" on public.drinks for all to authenticated using (private.is_admin()) with check (private.is_admin());
create policy "staff read addons" on public.addons for select to authenticated using (private.is_staff_member());
create policy "admins manage addons" on public.addons for all to authenticated using (private.is_admin()) with check (private.is_admin());
create policy "staff read food trays" on public.food_trays for select to authenticated using (private.is_staff_member());
create policy "admins manage food trays" on public.food_trays for all to authenticated using (private.is_admin()) with check (private.is_admin());
create policy "staff read food packs" on public.food_packs for select to authenticated using (private.is_staff_member());
create policy "admins manage food packs" on public.food_packs for all to authenticated using (private.is_admin()) with check (private.is_admin());
create policy "staff read lechon options" on public.lechon_options for select to authenticated using (private.is_staff_member());
create policy "admins manage lechon options" on public.lechon_options for all to authenticated using (private.is_admin()) with check (private.is_admin());

create policy "staff manage inquiries" on public.inquiries for all to authenticated using (private.is_staff_member()) with check (private.is_staff_member());
create policy "staff manage inquiry menu selections" on public.inquiry_menu_selections for all to authenticated using (private.is_staff_member()) with check (private.is_staff_member());
create policy "staff manage inquiry requirements" on public.inquiry_special_requirements for all to authenticated using (private.is_staff_member()) with check (private.is_staff_member());
create policy "staff manage inquiry notes" on public.inquiry_notes for all to authenticated using (private.is_staff_member()) with check (private.is_staff_member());
create policy "staff manage quotations" on public.quotations for all to authenticated using (private.is_staff_member()) with check (private.is_staff_member());
create policy "staff manage quotation items" on public.quotation_items for all to authenticated using (private.is_staff_member()) with check (private.is_staff_member());
create policy "staff manage quotation menu selections" on public.quotation_menu_selections for all to authenticated using (private.is_staff_member()) with check (private.is_staff_member());
create policy "staff manage reservations" on public.reservations for all to authenticated using (private.is_staff_member()) with check (private.is_staff_member());
create policy "staff manage reservation line items" on public.reservation_line_items for all to authenticated using (private.is_staff_member()) with check (private.is_staff_member());
create policy "staff manage payments" on public.payments for all to authenticated using (private.is_staff_member()) with check (private.is_staff_member());
create policy "staff manage internal tasks" on public.internal_tasks for all to authenticated using (private.is_staff_member()) with check (private.is_staff_member());
create policy "staff read feedback" on public.feedback for select to authenticated using (private.is_staff_member());
create policy "staff manage notifications" on public.notifications for all to authenticated using (private.is_staff_member()) with check (private.is_staff_member());
create policy "staff manage notification logs" on public.notification_logs for all to authenticated using (private.is_staff_member()) with check (private.is_staff_member());
create policy "staff read activity logs" on public.activity_logs for select to authenticated using (private.is_staff_member());
create policy "staff insert activity logs" on public.activity_logs for insert to authenticated with check (private.is_staff_member());
create policy "admins read audit logs" on public.audit_logs for select to authenticated using (private.is_admin());
create policy "admins insert audit logs" on public.audit_logs for insert to authenticated with check (private.is_admin());

insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
values
  ('payment-proofs', 'payment-proofs', false, 5242880, array['image/jpeg', 'image/png', 'image/webp', 'application/pdf']),
  ('catalog-assets', 'catalog-assets', true, 5242880, array['image/jpeg', 'image/png', 'image/webp'])
on conflict (id) do update
set public = excluded.public,
    file_size_limit = excluded.file_size_limit,
    allowed_mime_types = excluded.allowed_mime_types;

create policy "public can read catalog assets" on storage.objects for select to anon, authenticated using (bucket_id = 'catalog-assets');
create policy "admins manage catalog assets" on storage.objects for all to authenticated using (bucket_id = 'catalog-assets' and private.is_admin()) with check (bucket_id = 'catalog-assets' and private.is_admin());
create policy "staff read payment proofs" on storage.objects for select to authenticated using (bucket_id = 'payment-proofs' and private.is_staff_member());
create policy "staff upload payment proofs" on storage.objects for insert to authenticated with check (bucket_id = 'payment-proofs' and private.is_staff_member());

grant usage on schema public to anon, authenticated;
grant usage on schema private to authenticated;
grant select on public.catering_packages, public.package_inclusions, public.dish_categories, public.dishes, public.drinks, public.addons, public.food_trays, public.food_packs, public.lechon_options to anon, authenticated;
grant all on all tables in schema public to authenticated;
grant execute on function public.can_customer_edit_inquiry(uuid) to anon, authenticated;
grant execute on function public.generate_reference_code() to authenticated;
grant execute on function public.generate_quotation_number() to authenticated;
grant execute on function public.generate_reservation_code() to authenticated;
