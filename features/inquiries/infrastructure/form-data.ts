import { uniqueValues } from "@/lib/catering";

export function readText(form: FormData, key: string) {
  const value = form.get(key);
  return typeof value === "string" ? value.trim() : "";
}

export function readNullableText(form: FormData, key: string) {
  const value = readText(form, key);
  return value || null;
}

export function readNumber(form: FormData, key: string) {
  return Number(readText(form, key));
}

export function readJsonArray(form: FormData, key: string) {
  const value = readText(form, key);
  if (!value) return [];

  try {
    const parsed = JSON.parse(value);
    return Array.isArray(parsed) ? uniqueValues(parsed.map(String)) : [];
  } catch {
    return [];
  }
}
