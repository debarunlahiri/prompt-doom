import { api } from "../core/api.js";
import {
  $,
  $$,
  closeModal,
  dataTable,
  emptyTableRow,
  escapeHtml,
  filterBar,
  hero,
  icon,
  openModal,
  panel,
  refreshIcons,
  toast,
} from "../components/ui.js";

function editor(config, item, reload) {
  openModal(
    `${item ? "Edit" : "Add"} ${config.singular}`,
    `<form id="taxonomy-form" class="taxonomy-form"><label>Name<input name="name" value="${escapeHtml(item?.name || "")}" placeholder="Enter ${config.singular} name" required></label>${config.id === "categories" ? `<label>Description<textarea name="description" placeholder="Describe this category">${escapeHtml(item?.description || "")}</textarea></label>` : ""}<label>Status<select class="select-control" name="status"><option ${item?.status !== "inactive" ? "selected" : ""}>active</option><option ${item?.status === "inactive" ? "selected" : ""}>inactive</option></select></label><div class="form-actions"><button type="button" class="button" data-close-modal>Cancel</button><button class="primary">${icon("save")} Save</button></div></form>`,
    config.id === "tags" ? "modal-small" : "modal-compact",
  );
  $("#taxonomy-form").onsubmit = async (event) => {
    event.preventDefault();
    await api(`/admin/${config.id}${item ? `/${item.id}` : ""}`, {
      method: item ? "PATCH" : "POST",
      body: JSON.stringify(Object.fromEntries(new FormData(event.target))),
    });
    closeModal();
    toast(`${config.singular} saved.`);
    await reload();
  };
  refreshIcons();
}

export function createTaxonomyPage(config) {
  return {
    ...config,
    kicker: "ORGANISATION",
    async render() {
      const data = await api(`/admin/${config.id}`);
      $("#content").innerHTML =
        hero(
          config.label,
          config.description,
          `<button id="add-taxonomy" class="primary">${icon("plus")} Add ${config.singular}</button>`,
        ) +
        panel(
          filterBar(
            `<label>Search<input id="taxonomy-search" placeholder="Search ${config.id}"></label><label>Status<select id="taxonomy-status" class="select-control"><option value="">All statuses</option><option>active</option><option>inactive</option></select></label>`,
          ) +
            dataTable(
              [
                "Name",
                config.id === "categories" ? "Description" : "Slug",
                "Status",
                "Actions",
              ],
              "",
              "data-table-spacing",
              "taxonomy-rows",
            ),
        );
      const draw = () => {
        const query = $("#taxonomy-search").value.toLowerCase();
        const status = $("#taxonomy-status").value;
        const items = data.items.filter(
          (item) =>
            (!query ||
              `${item.name} ${item.description || item.slug}`
                .toLowerCase()
                .includes(query)) &&
            (!status || item.status === status),
        );
        $("#taxonomy-rows").innerHTML =
          items
            .map(
              (item) =>
                `<tr><td><strong>${escapeHtml(item.name)}</strong></td><td>${escapeHtml(config.id === "categories" ? item.description || "—" : `/${item.slug}`)}</td><td><span class="badge ${item.status}">${item.status}</span></td><td><button class="button icon" data-edit-taxonomy="${item.id}">${icon("pencil")}</button> <button class="button danger icon" data-delete-taxonomy="${item.id}">${icon("trash-2")}</button></td></tr>`,
            )
            .join("") || emptyTableRow(4, `No ${config.id} found`);
        $$("[data-edit-taxonomy]").forEach((button) => {
          button.onclick = () =>
            editor(
              config,
              items.find(
                (item) => item.id === Number(button.dataset.editTaxonomy),
              ),
              () => this.render(),
            );
        });
        $$("[data-delete-taxonomy]").forEach((button) => {
          button.onclick = async () => {
            if (!window.confirm(`Delete this ${config.singular}?`)) return;
            await api(`/admin/${config.id}/${button.dataset.deleteTaxonomy}`, {
              method: "DELETE",
            });
            toast(`${config.singular} deleted.`);
            await this.render();
          };
        });
        refreshIcons();
      };
      $("#add-taxonomy").onclick = () =>
        editor(config, null, () => this.render());
      $("#taxonomy-search").oninput = draw;
      $("#taxonomy-status").onchange = draw;
      draw();
    },
  };
}
