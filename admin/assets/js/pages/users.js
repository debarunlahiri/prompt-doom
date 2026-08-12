import { api } from "../core/api.js";
import {
  $,
  $$,
  escapeHtml,
  dataTable,
  emptyTableRow,
  filterBar,
  formatDate,
  hero,
  icon,
  panel,
  panelHeader,
  refreshIcons,
  toast,
} from "../components/ui.js?v=20260811-1";

function rows(items) {
  return (
    items
      .map(
        (user) =>
          `<tr><td><div class="person"><div class="avatar">${escapeHtml((user.name || user.email)[0])}</div><div><strong>${escapeHtml(user.name)}</strong><small>${escapeHtml(user.email)}</small></div></div></td><td><span class="badge ${user.status}">${user.status}</span></td><td>${formatDate(user.lastLoginAt)}</td><td>${formatDate(user.createdAt)}</td><td><button class="button ${user.status === "active" ? "danger" : ""}" data-user="${user.id}" data-status="${user.status === "active" ? "blocked" : "active"}">${icon(user.status === "active" ? "lock-keyhole" : "check")} ${user.status === "active" ? "Block" : "Unblock"}</button></td></tr>`,
      )
      .join("") || emptyTableRow(5, "No users found")
  );
}

function bindActions(reload) {
  $$("[data-user]").forEach((button) => {
    button.onclick = async () => {
      await api(`/admin/users/${button.dataset.user}/status`, {
        method: "PATCH",
        body: JSON.stringify({ status: button.dataset.status }),
      });
      toast("User status updated.");
      await reload();
    };
  });
  refreshIcons();
}

export const usersPage = {
  id: "users",
  label: "Users",
  icon: "users",
  kicker: "MANAGEMENT",
  async render() {
    const data = await api("/admin/users?limit=100");
    $("#content").innerHTML =
      hero("User management", "Search accounts and control access.") +
      panel(
        panelHeader(
          "All users",
          `${data.pagination.total} registered accounts`,
        ) +
          filterBar(
            '<label>Search<input id="user-search" placeholder="Search name or email"></label>',
          ) +
          dataTable(
            ["User", "Status", "Last login", "Joined", "Actions"],
            rows(data.items),
            "data-table-spacing",
            "user-rows",
          ),
      );
    $("#user-search").oninput = (event) => {
      const filtered = data.items.filter((user) =>
        `${user.name} ${user.email}`
          .toLowerCase()
          .includes(event.target.value.toLowerCase()),
      );
      $("#user-rows").innerHTML = rows(filtered);
      bindActions(() => this.render());
    };
    bindActions(() => this.render());
  },
};
