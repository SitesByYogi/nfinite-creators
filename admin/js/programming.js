(function () {
  'use strict';
  const config = window.NfiniteProgramming;
  if (!config) return;
  const $ = id => document.getElementById(id);
  const picks = [...config.rule.picks], exclude = [...config.rule.exclude];
  const labels = config.labels, results = new Map();
  let page = 0, generation = 0, dirty = false;
  function button(text, action) {
    const b = document.createElement('button'); b.type = 'button'; b.className = 'button';
    b.textContent = text; b.style.margin = '0 4px'; b.addEventListener('click', action); return b;
  }
  function sync() { $('pod-rules').value = JSON.stringify({ picks, exclude }); }
  function change() { dirty = true; paint(); }
  function choose(id, target) {
    const other = target === picks ? exclude : picks;
    const old = other.indexOf(id); if (old >= 0) other.splice(old, 1);
    if (!target.includes(id)) target.push(id);
    change();
  }
  function paint() {
    for (const [target, rootId] of [[picks, 'pod-picks'], [exclude, 'pod-exclusions']]) {
      const root = $(rootId); root.replaceChildren();
      if (!target.length) root.textContent = 'No items selected.';
      target.forEach((id, index) => {
        const row = document.createElement('p');
        const label = document.createElement('span'); label.textContent = `${index + 1}. ${labels[id] || `Item #${id}`}`; row.append(label);
        if (target === picks) {
          for (const [title, offset] of [['Up', -1], ['Down', 1]]) {
            const b = button(title, () => { const at = index + offset; [target[index], target[at]] = [target[at], target[index]]; change(); });
            b.disabled = index + offset < 0 || index + offset >= target.length; row.append(b);
          }
        }
        row.append(button('Remove', () => { target.splice(index, 1); change(); })); root.append(row);
      });
    }
    sync();
  }
  async function search(reset) {
    const token = ++generation;
    if (reset) { page = 0; results.clear(); $('pod-search-results').replaceChildren(); }
    $('pod-search-status').textContent = 'Loading content…'; $('pod-more').disabled = true;
    const url = new URL(config.url, location.href);
    const params = { action: 'nfinite_programming_search', nonce: config.nonce, surface: config.surface, q: $('pod-search').value.trim(), release: $('pod-release')?.value || '', paged: page + 1 };
    Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
    try {
      const response = await fetch(url); const body = await response.json();
      if (token !== generation) return;
      if (!response.ok || !body.success) throw new Error('Could not load content. Refresh your login and retry.');
      page++;
      for (const item of body.data.items) {
        if (results.has(item.id)) continue;
        results.set(item.id, item); labels[item.id] = item.label;
        const row = document.createElement('p'); const label = document.createElement('span'); label.textContent = item.label;
        row.append(label, button('Pick', () => choose(item.id, picks)), button('Exclude', () => choose(item.id, exclude)));
        $('pod-search-results').append(row);
      }
      $('pod-more').hidden = !body.data.more;
      $('pod-search-status').textContent = `${results.size} results loaded. Picks and exclusions are saved separately for each section.`;
    } catch (error) { if (token === generation) $('pod-search-status').textContent = error.message; }
    finally { if (token === generation) $('pod-more').disabled = false; }
  }
  $('pod-find').addEventListener('click', () => search(true));
  $('pod-search').addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); search(true); } });
  $('pod-more').addEventListener('click', () => search(false));
  $('pod-pick-all').addEventListener('click', () => { for (const id of results.keys()) choose(id, picks); });
  $('pod-exclude-all').addEventListener('click', () => { for (const id of results.keys()) choose(id, exclude); });
  $('pod-programming-form').addEventListener('change', () => { dirty = true; });
  $('pod-programming-form').addEventListener('submit', () => { sync(); dirty = false; });
  window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
  paint(); search(true);
})();
