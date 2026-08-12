import { createTaxonomyPage } from "./taxonomy.js?v=20260811-1";
export const tagsPage = createTaxonomyPage({
  id: "tags",
  label: "Tags",
  singular: "tag",
  icon: "tags",
  description: "Maintain searchable labels used across prompt assets.",
});
