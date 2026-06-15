export function toggleSelection(list: string[], id: string, limit?: number) {
  if (list.includes(id)) return list.filter((item) => item !== id);
  if (limit && list.length >= limit) return list;
  return [...list, id];
}
