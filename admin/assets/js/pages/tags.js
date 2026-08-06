import { createTaxonomyPage } from "./taxonomy.js";
export const tagsPage = createTaxonomyPage({
  id: "tags",
  label: "Tags",
  singular: "tag",
  icon: "tags",
  description: "Maintain searchable labels used across prompt assets.",
});
