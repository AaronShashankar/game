// Shop page: catalogue, prices and ownership all come from /api/shop;
// purchases go through /api/shop/buy (the server charges its own price).
(function () {
  const coinDisplay = document.getElementById('coinCount');
  const message = document.getElementById('shopMessage');

  function say(text) {
    message.textContent = text;
    clearTimeout(say.t);
    say.t = setTimeout(() => (message.textContent = ''), 3000);
  }

  function render({ items, wallet }) {
    coinDisplay.textContent = wallet.coins;
    document.querySelectorAll('.item-grid[data-kinds]').forEach((grid) => {
      const kinds = grid.dataset.kinds.split(',');
      grid.innerHTML = '';
      items.filter((i) => kinds.includes(i.kind)).forEach((item) => {
        const cosmetic = item.kind === 'skin' || item.kind === 'theme';
        const owned = cosmetic && item.owned > 0;
        const div = document.createElement('div');
        div.className = 'item';
        div.innerHTML = `
          <div class="item-icon">${App.escape(item.icon)}</div>
          <h3>${App.escape(item.name)}</h3>
          <p>${App.escape(item.description)}</p>
          ${!cosmetic && item.owned ? `<p class="owned-count">You have ${item.owned}</p>` : ''}
          <button class="buy-btn"></button>`;
        const btn = div.querySelector('button');
        if (!item.active) {
          btn.textContent = 'Coming soon';
          btn.disabled = true;
          btn.classList.add('owned');
        } else if (owned) {
          btn.textContent = 'Owned';
          btn.classList.add('owned');
        } else {
          btn.textContent = `Buy – ${item.price}💰`;
          btn.disabled = wallet.coins < item.price;
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
      say('Could not load the shop: ' + e.message);
    }
  }

  async function buy(item, btn) {
    btn.disabled = true;
    try {
      await App.api('/api/shop/buy', { body: { item: item.code } });
      say(`✅ Bought ${item.name}!`);
      await load();
    } catch (e) {
      say('❌ ' + e.message);
      btn.disabled = false;
    }
  }

  load();
})();
