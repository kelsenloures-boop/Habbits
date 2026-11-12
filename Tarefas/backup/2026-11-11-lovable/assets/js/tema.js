/* tema.js — alternância de tema (dark, light, purple) */

document.addEventListener('DOMContentLoaded', () => {
  const switchEl = document.getElementById('temaSwitch');
  if (!switchEl) return; // página sem o botão de troca de tema (ex.: login)

  /* tema inicial — garantido pelo script inline em html_inicio/login.php */
  const atual = document.documentElement.getAttribute('data-tema') || 'dark';
  const ativo = switchEl.querySelector(`.tema-btn[data-tema="${atual}"]`);
  if (ativo) ativo.classList.add('ativo');

  /* clique no botão do switch */
  switchEl.addEventListener('click', (e) => {
    const btn = e.target.closest('.tema-btn');
    if (!btn) return;
    const novoTema = btn.getAttribute('data-tema');
    if (!novoTema) return;

    /* atualize os botões ativos */
    switchEl.querySelectorAll('.tema-btn').forEach(b => b.classList.remove('ativo'));
    btn.classList.add('ativo');

    /* aplique o tema */
    document.documentElement.setAttribute('data-tema', novoTema);
    try { localStorage.setItem('habbits_tema', novoTema); } catch (_) {}
  });

  /* respeite prefers-reduced-motion */
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.documentElement.style.setProperty('--transicao-tema', 'none');
  }
});