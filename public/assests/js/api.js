export const API = {
    base: "/crmfms/api",
    async get(path) {
      const r = await fetch(`${this.base}${path}`, { credentials: "include" });
      if (!r.ok) throw new Error(await r.text());
      return r.json();
    },
    async post(path, body) {
      const r = await fetch(`${this.base}${path}`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify(body || {})
      });
      if (!r.ok) throw new Error(await r.text());
      return r.json();
    }
  };
  