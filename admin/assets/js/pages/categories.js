import { createTaxonomyPage } from "./taxonomy.js";
export const categoriesPage = createTaxonomyPage({
  id: "categories",
  label: "Categories",
  singular: "category",
  icon: "folder-tree",
  description: "Organise the gallery into browsable collections.",
});
