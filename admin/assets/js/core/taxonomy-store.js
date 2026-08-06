import { api } from "./api.js";
import { state } from "./state.js";

export async function loadTaxonomies() {
  const [categories, tags] = await Promise.all([
    api("/admin/categories"),
    api("/admin/tags"),
  ]);
  state.categories = categories.items;
  state.tags = tags.items;
  return { categories: state.categories, tags: state.tags };
}
