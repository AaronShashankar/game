// Shop page: catalogue, prices and ownership all come from /api/shop;
// purchases go through /api/shop/buy (the server charges its own price).
(function () {
  const ICONS = {
    timeFreeze: 'snowflake', hint: 'search', skip: 'skip', scoreDoubler: 'trend',
    pack: 'package', megaPack: 'gem', skin2: 'shirt', skin3: 'shirt',
    forest: 'image', space: 'image', speedBoost: 'zap', shield: 'shield', streakFreeze: 'flame',
  };
  const MAX = { streakFreeze: window.ShopRules?.streakFreezeMax ?? 2 };

  function render({ items, wallet }) {
    document.getElementById('coinCount').textContent = wallet.coins.toLocaleString();
    App.setCoins(wallet.coins);
    document.querySelectorAll('.item-grid[data-kinds]').forEach((grid) => {
      const kinds = grid.dataset.kinds.split(',');
      grid.innerHTML = '';
      items.filter((i) => kinds.includes(i.kind)).forEach((item) => {
        const cosmetic = item.kind === 'skin' || item.kind === 'theme';
        const owned = cosmetic && item.owned > 0;
        const equipped = owned && wallet.equipped[item.kind] === item.code;
        const atMax = MAX[item.code] !== undefined && item.owned >= MAX[item.code];
        const div = document.createElement('div');
        div.className = 'card item';
        div.innerHTML = `
          <div class="row-between">
            <div class="icon-box">${App.icon(ICONS[item.code] || 'package')}</div>
            ${!cosmetic && item.owned ? `<span class="chip">You have ${item.owned}</span>` : ''}
            ${equipped ? `<span class="chip chip-accent">${App.icon('check')}Equipped</span>` : owned ? '<span class="chip">Owned</span>' : ''}
            ${!item.active ? '<span class="chip">Coming soon</span>' : ''}
          </div>
          <div>
            <h3>${App.escape(item.name)}</h3>
            <p>${App.escape(item.description)}</p>
          </div>
          <div class="item-foot">
            <span class="price">${App.icon('coins')}${item.price}</span>
            <button class="btn btn-secondary btn-sm"></button>
          </div>`;
        const btn = div.querySelector('button');
        if (!item.active) {
          btn.textContent = 'Unavailable';
          btn.disabled = true;
        } else if (owned) {
          // Owned cosmetics toggle between this and the default look.
          btn.textContent = equipped ? 'Unequip' : 'Equip';
          btn.className = equipped ? 'btn btn-ghost btn-sm' : 'btn btn-secondary btn-sm';
          btn.addEventListener('click', () => equip(item, equipped ? '' : item.code, btn));
        } else if (atMax) {
          btn.textContent = `Max ${MAX[item.code]}`;
          btn.disabled = true;
        } else {
          btn.textContent = 'Buy';
          btn.disabled = wallet.coins < item.price;
          if (btn.disabled) btn.title = `You need ${item.price - wallet.coins} more coins`;
          btn.addEventListener('click', () => buy(item, btn));
        }
        grid.appendChild(div);
      });
    });
  }

  async function load() {
    try {
      render(await App.api('/api/shop'));
    } catch (e) {
      App.toast('Could not load the shop: ' + e.message, 'danger');
    }
  }

  async function buy(item, btn) {
    btn.disabled = true;
    try {
      await App.api('/api/shop/buy', { body: { item: item.code } });
      App.toast(`Bought ${item.name}`, 'success');
      await load();
    } catch (e) {
      App.toast(e.message, 'danger');
      btn.disabled = false;
    }
  }

  async function equip(item, code, btn) {
    btn.disabled = true;
    try {
      await App.api('/api/shop/equip', { body: { kind: item.kind, item: code } });
      App.toast(code ? `${item.name} equipped` : 'Back to the default look', 'success');
      await load();
    } catch (e) {
      App.toast(e.message, 'danger');
      btn.disabled = false;
    }
  }

  load();
})();
