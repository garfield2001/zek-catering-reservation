-- Development-only catalog seed.
-- Do not run this against production. Production should apply migrations only,
-- then owner/admin can manage catalog and settings from the admin UI.

insert into public.business_settings (
  business_name,
  business_phone,
  business_email,
  business_address,
  minimum_booking_days,
  customer_edit_window_hours,
  quotation_validity_days,
  reservation_fee_type,
  reservation_fee_value,
  reservation_fee_due_days,
  final_payment_due_days,
  default_city
)
select
  'Zek Catering',
  '09195255555',
  'admin@zekcatering.local',
  'Aparente Street, Brgy. City Heights, General Santos City',
  14,
  24,
  3,
  'fixed',
  5000,
  3,
  7,
  'General Santos City'
where not exists (select 1 from public.business_settings);

insert into public.catering_packages (
  name,
  slug,
  description,
  price_per_pax,
  base_price,
  minimum_pax,
  minimum_guests,
  pax_increment,
  meal_slots,
  drink_slots,
  rice_included,
  sort_order
)
values
  ('Package A', 'package-a', '4 meal choices, 1 drink, rice included.', 280, 280, 50, 50, 50, 4, 1, true, 1),
  ('Package B', 'package-b', '5 meal choices, 1 drink, rice included.', 310, 310, 50, 50, 50, 5, 1, true, 2),
  ('Package C', 'package-c', '6 meal choices, 1 drink, rice included.', 350, 350, 50, 50, 50, 6, 1, true, 3),
  ('Package D', 'package-d', '7 meal choices, 1 drink, rice included.', 380, 380, 50, 50, 50, 7, 1, true, 4)
on conflict (slug) do update
set description = excluded.description,
    price_per_pax = excluded.price_per_pax,
    base_price = excluded.base_price,
    minimum_pax = excluded.minimum_pax,
    minimum_guests = excluded.minimum_guests,
    pax_increment = excluded.pax_increment,
    meal_slots = excluded.meal_slots,
    drink_slots = excluded.drink_slots,
    rice_included = excluded.rice_included,
    sort_order = excluded.sort_order,
    is_active = true;

with inclusions(label, sort_order) as (
  values
    ('Elegant buffet setup with food lamps', 1),
    ('Tables and chairs according to motif', 2),
    ('Table flower centerpiece', 3),
    ('Water dispenser with distilled water', 4),
    ('Table for cake', 5),
    ('Table for gifts', 6),
    ('Lechon tray', 7),
    ('Uniformed service waiter', 8),
    ('1 round softdrinks', 9),
    ('Steamed rice', 10),
    ('Catering service for 4 hours', 11)
)
insert into public.package_inclusions (package_id, label, sort_order)
select p.id, i.label, i.sort_order
from public.catering_packages p
cross join inclusions i
where not exists (
  select 1
  from public.package_inclusions existing
  where existing.package_id = p.id
    and existing.label = i.label
);

insert into public.dish_categories (name, sort_order)
values
  ('Beef', 1),
  ('Pork', 2),
  ('Chicken', 3),
  ('Fish & Seafoods', 4),
  ('Vegetables', 5),
  ('Noodles', 6),
  ('Pasta', 7),
  ('Dessert', 8)
on conflict (name) do update
set sort_order = excluded.sort_order,
    is_active = true;

with seed_dishes(category_name, dish_name, description, dish_type, premium_pricing_mode, premium_price, contains_pork) as (
  values
    ('Beef', 'Beef Steak Tagalog', 'Classic Filipino beef steak with onions.', 'standard', 'none', 0, false),
    ('Beef', 'Beef Caldereta', 'Tomato-based beef stew for celebrations.', 'standard', 'none', 0, false),
    ('Beef', 'Beef with Mushroom', 'Savory beef with mushroom sauce.', 'premium', 'per_pax', 10, false),
    ('Pork', 'Pork Menudo', 'Home-style pork menudo.', 'standard', 'none', 0, true),
    ('Pork', 'Lumpia Shanghai', 'Crispy bite-sized spring rolls.', 'standard', 'none', 0, true),
    ('Chicken', 'Garlic Chicken', 'Fried chicken tossed in garlic seasoning.', 'standard', 'none', 0, false),
    ('Chicken', 'Chicken Afritada', 'Chicken stew with vegetables.', 'standard', 'none', 0, false),
    ('Chicken', 'Chicken Cordon Bleu', 'Stuffed chicken with creamy sauce.', 'premium', 'per_pax', 15, false),
    ('Fish & Seafoods', 'Mixed Seafoods', 'Mixed seafood dish for buffet service.', 'premium', 'per_pax', 20, false),
    ('Fish & Seafoods', 'Sweet and Sour Fish', 'Fried fish with sweet and sour sauce.', 'standard', 'none', 0, false),
    ('Vegetables', 'Chopsuey with Quail Egg', 'Mixed vegetables with quail egg.', 'standard', 'none', 0, false),
    ('Vegetables', 'Buttered Mix Vegetables', 'Buttered mixed vegetables.', 'standard', 'none', 0, false),
    ('Noodles', 'Sotanghon Guisado', 'Stir-fried glass noodles.', 'standard', 'none', 0, false),
    ('Noodles', 'Pancit Canton', 'Classic pancit canton.', 'standard', 'none', 0, false),
    ('Pasta', 'Creamy Carbonara', 'Cream-based pasta dish.', 'standard', 'none', 0, false),
    ('Dessert', 'Buko Salad', 'Creamy young coconut dessert.', 'standard', 'none', 0, false),
    ('Dessert', 'Mango Sago', 'Mango dessert with sago pearls.', 'standard', 'none', 0, false),
    ('Dessert', 'Fresh Mix Fruits', 'Assorted fresh fruits.', 'standard', 'none', 0, false)
)
insert into public.dishes (category_id, name, description, dish_type, premium_pricing_mode, premium_price, contains_pork)
select c.id, s.dish_name, s.description, s.dish_type::public.dish_type, s.premium_pricing_mode::public.pricing_mode, s.premium_price, s.contains_pork
from seed_dishes s
join public.dish_categories c on c.name = s.category_name
on conflict (category_id, name) do update
set description = excluded.description,
    dish_type = excluded.dish_type,
    premium_pricing_mode = excluded.premium_pricing_mode,
    premium_price = excluded.premium_price,
    contains_pork = excluded.contains_pork,
    status = 'available',
    is_active = true;

insert into public.drinks (name, description)
values
  ('Iced Tea', 'Buffet drink option.'),
  ('Pineapple Juice', 'Buffet drink option.'),
  ('Orange Juice', 'Buffet drink option.'),
  ('Distilled Water', 'Individual water option.')
on conflict (name) do update
set description = excluded.description,
    status = 'available',
    is_active = true;

insert into public.addons (name, description, category, pricing_mode, price)
select seed.name, seed.description, seed.category, seed.pricing_mode::public.pricing_mode, seed.price
from (
  values
    ('Pica-Pica Package', '30 pax pica-pica set.', 'Party add-on', 'flat', 4500),
    ('Additional Service Hour', 'Extra catering service hour, subject to staff availability.', 'Service', 'manual', 0),
    ('Transportation Fee', 'Manual transportation fee based on venue distance and accessibility.', 'Manual charge', 'manual', 0)
) as seed(name, description, category, pricing_mode, price)
where not exists (
  select 1 from public.addons existing where existing.name = seed.name
);

insert into public.food_packs (name, description, price_per_pack, minimum_packs, includes)
select seed.name, seed.description, seed.price_per_pack, seed.minimum_packs, seed.includes
from (
  values
    ('Meal A', 'Fried chicken, chopsuey, rice, distilled water.', 129, 10, array['Fried Chicken', 'Chopsuey', 'Rice', 'Distilled Water']),
    ('Meal D', 'Garlic chicken, chopsuey, lumpia shanghai, rice, distilled water.', 149, 10, array['Garlic Chicken', 'Chopsuey', 'Lumpia Shanghai', 'Rice', 'Distilled Water']),
    ('Meal G', 'Beef steak, garlic chicken, sotanghon guisado, lumpia shanghai, rice, distilled water.', 169, 10, array['Beef Steak', 'Garlic Chicken', 'Sotanghon Guisado', 'Lumpia Shanghai', 'Rice', 'Distilled Water'])
) as seed(name, description, price_per_pack, minimum_packs, includes)
where not exists (
  select 1 from public.food_packs existing where existing.name = seed.name
);

insert into public.food_trays (name, category, price_per_tray, good_for_min, good_for_max)
select seed.name, seed.category, seed.price_per_tray, seed.good_for_min, seed.good_for_max
from (
  values
    ('Beef Caldereta Tray', 'Beef', 1100, 12, 15),
    ('Garlic Chicken Tray', 'Chicken', 750, 12, 15),
    ('Lumpia Shanghai Tray', 'Pork', 600, 12, 15),
    ('Buko Salad Tray', 'Dessert', 700, 12, 15),
    ('Pancit Canton Tray', 'Noodles', 600, 12, 15)
) as seed(name, category, price_per_tray, good_for_min, good_for_max)
where not exists (
  select 1 from public.food_trays existing where existing.name = seed.name
);

insert into public.lechon_options (name, weight_kg, price, description)
select seed.name, seed.weight_kg, seed.price, seed.description
from (
  values
    ('Lechon Belly 3kg', 3, 1950, 'Uncooked weight.'),
    ('Lechon Belly 3.5kg', 3.5, 2350, 'Uncooked weight.'),
    ('Lechon Belly 4kg', 4, 2650, 'Uncooked weight.'),
    ('Lechon Belly 5kg', 5, 3300, 'Uncooked weight.'),
    ('Lechon Belly 6kg', 6, 3950, 'Uncooked weight.')
) as seed(name, weight_kg, price, description)
where not exists (
  select 1 from public.lechon_options existing where existing.name = seed.name
);
