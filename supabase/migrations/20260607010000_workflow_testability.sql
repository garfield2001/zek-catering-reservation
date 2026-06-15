alter table public.business_settings
  add column if not exists payment_instruction_title text not null default 'Reservation fee payment instructions',
  add column if not exists gcash_account_name text,
  add column if not exists gcash_account_number text,
  add column if not exists paymaya_account_name text,
  add column if not exists paymaya_account_number text,
  add column if not exists bank_account_details text,
  add column if not exists payment_notes text default 'Upload a clear screenshot or PDF after sending your reservation fee. GCash, Maya/PayMaya, bank transfer, and other Philippine payment apps are accepted for staff verification.',
  add column if not exists email_enabled boolean not null default false,
  add column if not exists sms_enabled boolean not null default false,
  add column if not exists cancellation_policy_text text default 'Reservation fees are non-refundable unless management explicitly approves otherwise.',
  add column if not exists refund_policy_text text default 'Refunds and credits are subject to management review and approval.';

alter table public.reservations
  add column if not exists package_id uuid references public.catering_packages(id) on delete set null,
  add column if not exists guest_count integer,
  add column if not exists event_start_time time,
  add column if not exists deposit_required numeric(12, 2) not null default 0,
  add column if not exists deposit_paid numeric(12, 2) not null default 0,
  add column if not exists discount_amount numeric(12, 2) not null default 0;

alter table public.quotations
  add column if not exists discount_amount numeric(12, 2) not null default 0,
  add column if not exists terms_snapshot text;

alter table public.quotation_menu_selections
  add column if not exists item_id uuid,
  add column if not exists is_premium_snapshot boolean not null default false,
  add column if not exists sort_order integer not null default 0;

alter table public.payments
  add column if not exists paid_at timestamptz;

alter table public.internal_tasks
  add column if not exists created_by uuid references public.app_profiles(id) on delete set null;

alter table public.activity_logs
  add column if not exists actor_id uuid references public.app_profiles(id) on delete set null;

create index if not exists idx_reservations_package_id on public.reservations(package_id);
create index if not exists idx_internal_tasks_created_by on public.internal_tasks(created_by);
create index if not exists idx_activity_logs_actor_id on public.activity_logs(actor_id);

create or replace function private.is_admin()
returns boolean
language sql
security definer
set search_path = public, private
stable
as $$
  select coalesce(private.current_app_role() in ('admin', 'staff'), false);
$$;
