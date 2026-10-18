'use strict';

/* ==========================================================================
   Pokédex & Team Builder - Front-end do Trabalho Individual II
   Consome a API em /api (sessão por cookie + token CSRF).
   Todo texto vindo da API é inserido com textContent (proteção contra XSS).
   ========================================================================== */

const CATEGORIAS = [
	['iniciais', 'Iniciais'], ['roedores', 'Roedores'], ['pikaclones', 'Pikaclones'], ['aves', 'Aves'],
	['insetos', 'Insetos'], ['peixes', 'Peixes'], ['artificiais', 'Artificiais'], ['lendarios', 'Lendários'],
];
const MAX_TIME = 6;

const regrasTipos = {
	"Fogo": { vantagens: ["Planta", "Inseto", "Aço", "Gelo"], fraquezas: ["Água", "Terrestre", "Pedra"] },
	"Água": { vantagens: ["Fogo", "Terrestre", "Pedra"], fraquezas: ["Planta", "Elétrico"] },
	"Planta": { vantagens: ["Água", "Terrestre", "Pedra"], fraquezas: ["Fogo", "Gelo", "Voador", "Inseto", "Venenoso"] },
	"Venenoso": { vantagens: ["Planta", "Fada"], fraquezas: ["Terrestre", "Psíquico"] },
	"Elétrico": { vantagens: ["Água", "Voador"], fraquezas: ["Terrestre"] },
	"Normal": { vantagens: [], fraquezas: ["Lutador"] },
	"Voador": { vantagens: ["Planta", "Lutador", "Inseto"], fraquezas: ["Elétrico", "Gelo", "Pedra"] },
	"Inseto": { vantagens: ["Planta", "Psíquico", "Sombrio"], fraquezas: ["Fogo", "Voador", "Pedra"] },
	"Fada": { vantagens: ["Lutador", "Dragão", "Sombrio"], fraquezas: ["Venenoso", "Aço"] },
	"Gelo": { vantagens: ["Planta", "Terrestre", "Voador", "Dragão"], fraquezas: ["Fogo", "Lutador", "Pedra", "Aço"] },
	"Aço": { vantagens: ["Gelo", "Fada", "Pedra"], fraquezas: ["Fogo", "Lutador", "Terrestre"] },
	"Psíquico": { vantagens: ["Lutador", "Venenoso"], fraquezas: ["Inseto", "Fantasma", "Sombrio"] },
	"Dragão": { vantagens: ["Dragão"], fraquezas: ["Gelo", "Fada", "Dragão"] },
	"Terrestre": { vantagens: ["Fogo", "Elétrico", "Venenoso", "Pedra", "Aço"], fraquezas: ["Água", "Planta", "Gelo"] },
	"Sombrio": { vantagens: ["Psíquico", "Fantasma"], fraquezas: ["Lutador", "Inseto", "Fada"] },
	"Fantasma": { vantagens: ["Psíquico", "Fantasma"], fraquezas: ["Fantasma", "Sombrio"] },
	"Lutador": { vantagens: ["Normal", "Gelo", "Pedra", "Sombrio", "Aço"], fraquezas: ["Voador", "Psíquico", "Fada"] },
	"Pedra": { vantagens: ["Fogo", "Gelo", "Voador", "Inseto"], fraquezas: ["Água", "Planta", "Lutador", "Terrestre", "Aço"] }
};

// Estado da aplicação (a fonte da verdade é a API)
const estado = { usuario: null, pokemons: [], time: null };
let csrfToken = null;
let modoAuth = 'login';
let timerToast = null;

const $ = (id) => document.getElementById(id);

/* ---------- Utilitários ---------- */

/** Cria elementos sem innerHTML: textos entram como nós de texto. */
function el(tag, props = {}, ...filhos) {
	const no = document.createElement(tag);
	for (const [chave, valor] of Object.entries(props)) {
		if (valor === null || valor === undefined) continue;
		if (chave === 'class') no.className = valor;
		else if (chave.startsWith('on')) no.addEventListener(chave.slice(2), valor);
		else no.setAttribute(chave, valor);
	}
	for (const filho of filhos.flat()) {
		no.append(filho instanceof Node ? filho : document.createTextNode(String(filho)));
	}
	return no;
}

function toast(mensagem, erro = false) {
	const t = $('toast');
	t.textContent = mensagem;
	t.className = 'toast visivel' + (erro ? ' toast-erro' : '');
	clearTimeout(timerToast);
	timerToast = setTimeout(() => { t.className = 'toast'; }, 4500);
}

function textoErro(e) {
	const campos = e.campos ? Object.values(e.campos).join(' ') : '';
	return campos ? `${e.message}: ${campos}` : e.message;
}

/** Trata erros comuns; 401 = sessão expirada. */
function tratarErro(e) {
	if (e.status === 401 && estado.usuario) {
		estado.usuario = null;
		estado.time = null;
		csrfToken = null;
		renderTudo();
		toast('Sua sessão expirou. Entre novamente.', true);
		abrirAuth('login');
		return;
	}
	toast(textoErro(e), true);
}

/* ---------- Comunicação com a API ---------- */

async function getCsrf() {
	if (!csrfToken) {
		const resp = await fetch('api/csrf', { credentials: 'same-origin' });
		const json = await resp.json();
		csrfToken = json.data.csrf_token;
	}
	return csrfToken;
}

async function api(metodo, caminho, corpo, jaTentouDeNovo = false) {
	const cabecalhos = { 'Content-Type': 'application/json' };
	if (metodo !== 'GET') cabecalhos['X-CSRF-Token'] = await getCsrf();

	let resp;
	try {
		resp = await fetch('api' + caminho, {
			method: metodo,
			credentials: 'same-origin',
			headers: cabecalhos,
			body: corpo ? JSON.stringify(corpo) : undefined,
		});
	} catch (_) {
		throw Object.assign(new Error('Não foi possível falar com o servidor. O Apache está ligado?'), { status: 0, campos: {} });
	}

	let json = null;
	try { json = await resp.json(); } catch (_) { json = null; }

	if (!resp.ok || !json || json.success === false) {
		// token CSRF antigo (ex.: sessão renovada): busca outro e tenta uma vez
		if (resp.status === 403 && json && /CSRF/i.test(json.message || '') && !jaTentouDeNovo) {
			csrfToken = null;
			return api(metodo, caminho, corpo, true);
		}
		throw Object.assign(new Error((json && json.message) || 'Falha na comunicação com o servidor'), {
			status: resp.status,
			campos: (json && json.errors) || {},
		});
	}
	return json.data;
}

/* ---------- Carregamento de dados ---------- */

async function carregarSessao() {
	const s = await api('GET', '/auth/session');
	estado.usuario = s.autenticado ? s.usuario : null;
	if (estado.usuario) await carregarTime();
}

async function carregarCatalogo() {
	let pagina = 1;
	let totalPaginas = 1;
	const todos = [];
	do {
		const r = await api('GET', `/pokemons?page=${pagina}&limit=50`);
		todos.push(...r.items);
		totalPaginas = r.total_pages;
		pagina++;
	} while (pagina <= totalPaginas);
	estado.pokemons = todos;
}

async function carregarTime() {
	const times = await api('GET', '/times');
	estado.time = times.length ? times[0] : null;
}

/* ---------- Renderização ---------- */

const ehAdmin = () => estado.usuario && estado.usuario.perfil === 'admin';

function agrupar() {
	const porCategoria = new Map();
	for (const p of estado.pokemons) {
		const c = p.categoria || 'outros';
		if (!porCategoria.has(c)) porCategoria.set(c, []);
		porCategoria.get(c).push(p);
	}
	const rotulos = new Map(CATEGORIAS);
	const ordem = [
		...CATEGORIAS.map((c) => c[0]),
		...[...porCategoria.keys()].filter((k) => !rotulos.has(k)),
	];
	return ordem.filter((k) => porCategoria.has(k)).map((k) => ({
		slug: k.replace(/[^a-z0-9_-]/gi, '-'),
		rotulo: rotulos.get(k) || k.charAt(0).toUpperCase() + k.slice(1),
		itens: porCategoria.get(k),
	}));
}

function renderUsuario() {
	const area = $('area-usuario');
	area.replaceChildren();
	if (estado.usuario) {
		area.append(
			el('span', { class: 'saudacao' }, `Olá, ${estado.usuario.nome} `),
			el('span', { class: 'selo-perfil' }, estado.usuario.perfil),
			el('button', { class: 'btn-topo', type: 'button', onclick: sair }, 'Sair'),
		);
	} else {
		area.append(
			el('button', { class: 'btn-topo', type: 'button', onclick: () => abrirAuth('login') }, 'Entrar'),
			el('button', { class: 'btn-topo', type: 'button', onclick: () => abrirAuth('registro') }, 'Cadastrar'),
		);
	}
}

function renderMenus() {
	const grupos = agrupar();

	const menuTopo = $('menu-topo');
	menuTopo.replaceChildren(
		el('li', {}, el('a', { href: '#meu-time', class: 'link-menu' }, 'meu time')),
		...grupos.map((g) => el('li', {}, el('a', { href: `#cat-${g.slug}`, class: 'link-menu' }, g.rotulo.toLowerCase()))),
	);

	const menuLateral = $('menu-lateral');
	menuLateral.replaceChildren(
		el('li', {}, el('a', { href: '#home', class: 'link-categoria' }, 'HOME')),
		el('li', {}, el('a', { href: '#meu-time', class: 'link-categoria' }, 'MEU TIME')),
		...grupos.map((g) => el('li', {},
			el('details', { class: 'caixa-detalhes' },
				el('summary', { class: 'titulo-detalhes' }, g.rotulo.toUpperCase()),
				el('ul', { class: 'sublista-categoria' },
					...g.itens.map((p) => el('li', {},
						el('a', { href: `#pk-${p.id}`, onclick: () => revelarDescricao(p.id) }, p.nome))),
				),
			),
		)),
	);
}

function revelarDescricao(id) {
	const desc = document.querySelector(`#pk-${id} .descricao-pokemon`);
	if (desc) desc.classList.remove('oculto');
}

function cardPokemon(p) {
	const descricao = el('p', { class: 'descricao-pokemon oculto' }, p.descricao || 'Sem descrição cadastrada.');
	const numero = p.numero ? `#${String(p.numero).padStart(3, '0')} - ` : '';
	const figura = el('figure', { class: 'figura-pokemon' });
	if (p.imagem_url) {
		figura.append(el('img', {
			src: p.imagem_url, alt: `Imagem do ${p.nome}`, width: 215, height: 215, loading: 'lazy',
		}));
	}
	figura.append(el('figcaption', {}, `${numero}${p.tipo}`));

	const link = el('a', {
		href: `#pk-${p.id}`,
		class: 'link-card',
		onclick: (ev) => { ev.preventDefault(); descricao.classList.toggle('oculto'); },
	}, el('h3', {}, p.nome), figura);

	const card = el('article', { id: `pk-${p.id}`, class: 'card-pokemon' },
		link,
		descricao,
		el('button', { class: 'btn-adicionar', type: 'button', onclick: () => adicionarAoTime(p) }, '➕ Adicionar'),
	);
	if (ehAdmin()) {
		card.append(el('button', { class: 'btn-excluir', type: 'button', onclick: () => excluirPokemon(p) }, '🗑 Excluir do catálogo'));
	}
	return card;
}

function renderCatalogo() {
	const raiz = $('catalogo');
	raiz.replaceChildren();
	if (!estado.pokemons.length) {
		raiz.append(el('p', { class: 'msg-vazia' }, 'Nenhum Pokémon cadastrado no catálogo.'));
		return;
	}
	for (const g of agrupar()) {
		raiz.append(el('section', { id: `cat-${g.slug}`, class: 'secao-pokemon' },
			el('h2', {}, g.rotulo),
			el('div', { class: 'grid-pokemon' }, ...g.itens.map(cardPokemon)),
		));
	}
}

function renderTime() {
	const grid = $('grid-meu-time');
	const lista = estado.time ? estado.time.pokemons : [];
	grid.replaceChildren();
	$('titulo-time').textContent = `Meu Time (${lista.length}/${MAX_TIME})`;

	if (!estado.usuario) {
		grid.append(el('p', { class: 'msg-vazia' }, 'Entre na sua conta para montar e salvar o seu time.'));
	} else if (!lista.length) {
		grid.append(el('p', { class: 'msg-vazia' }, 'Seu time está vazio. Adicione Pokémon das categorias abaixo!'));
	} else {
		for (const p of lista) {
			const card = el('article', { class: 'card-time' });
			if (p.imagem_url) card.append(el('img', { src: p.imagem_url, alt: p.nome, width: 80, height: 80 }));
			card.append(
				el('h4', {}, p.nome),
				el('p', {}, el('small', {}, p.tipo)),
				el('button', { class: 'btn-remover', type: 'button', onclick: () => removerDoTime(p) }, '❌ Remover'),
			);
			grid.append(card);
		}
	}
	analisarTime(lista);
}

function analisarTime(lista) {
	const analise = $('analise-time');
	const listaVantagens = $('lista-vantagens');
	const listaFraquezas = $('lista-fraquezas');
	listaVantagens.replaceChildren();
	listaFraquezas.replaceChildren();

	if (!lista.length) {
		analise.classList.add('oculto');
		return;
	}
	analise.classList.remove('oculto');

	const forcas = new Set();
	const fraquezas = new Set();
	for (const p of lista) {
		for (const t of p.tipo.split('/')) {
			if (regrasTipos[t]) {
				regrasTipos[t].vantagens.forEach((v) => forcas.add(v));
				regrasTipos[t].fraquezas.forEach((f) => fraquezas.add(f));
			}
		}
	}
	const fraquezasReais = [...fraquezas].filter((f) => !forcas.has(f));

	if (forcas.size) [...forcas].forEach((f) => listaVantagens.append(el('li', {}, `✔️ ${f}`)));
	else listaVantagens.append(el('li', {}, 'Nenhuma vantagem clara'));

	if (fraquezasReais.length) fraquezasReais.forEach((f) => listaFraquezas.append(el('li', {}, `⚠️ ${f}`)));
	else listaFraquezas.append(el('li', {}, 'Nenhuma fraqueza crítica!'));
}

function renderPainelAdmin() {
	$('painel-admin').classList.toggle('oculto', !ehAdmin());
}

function renderTudo() {
	renderUsuario();
	renderMenus();
	renderCatalogo();
	renderTime();
	renderPainelAdmin();
}

/* ---------- Ações do time ---------- */

async function adicionarAoTime(p) {
	if (!estado.usuario) {
		toast('Entre na sua conta para adicionar Pokémon ao time.', true);
		abrirAuth('login');
		return;
	}
	try {
		if (!estado.time) estado.time = await api('POST', '/times', { nome: 'Meu Time' });
		estado.time = await api('POST', `/times/${estado.time.id}/pokemons`, { pokemon_id: p.id });
		renderTime();
		toast(`${p.nome} foi adicionado ao time!`);
	} catch (e) {
		tratarErro(e);
	}
}

async function removerDoTime(p) {
	try {
		estado.time = await api('DELETE', `/times/${estado.time.id}/pokemons/${p.id}`);
		renderTime();
		toast(`${p.nome} foi removido do time.`);
	} catch (e) {
		tratarErro(e);
	}
}

/* ---------- Ações do administrador ---------- */

async function excluirPokemon(p) {
	if (!confirm(`Excluir ${p.nome} do catálogo? Ele também sai dos times que o usam.`)) return;
	try {
		await api('DELETE', `/pokemons/${p.id}`);
		await carregarCatalogo();
		await carregarTime();
		renderTudo();
		toast(`${p.nome} foi excluído do catálogo.`);
	} catch (e) {
		tratarErro(e);
	}
}

async function cadastrarPokemon(ev) {
	ev.preventDefault();
	const campos = ev.target.elements;
	const erro = $('pokemon-erro');
	erro.textContent = '';

	const dados = {
		nome: campos.nome.value,
		tipo: campos.tipo.value,
		categoria: campos.categoria.value,
	};
	if (campos.numero.value) dados.numero = Number(campos.numero.value);
	if (campos.imagem_url.value) dados.imagem_url = campos.imagem_url.value;
	if (campos.descricao.value) dados.descricao = campos.descricao.value;

	try {
		await api('POST', '/pokemons', dados);
		ev.target.reset();
		await carregarCatalogo();
		renderTudo();
		toast(`${dados.nome} foi cadastrado no catálogo!`);
	} catch (e) {
		erro.textContent = textoErro(e);
		if (e.status === 401) tratarErro(e);
	}
}

/* ---------- Login, cadastro e logout ---------- */

function abrirAuth(modo) {
	modoAuth = modo;
	const registro = modo === 'registro';
	$('auth-titulo').textContent = registro ? 'Criar conta' : 'Entrar';
	$('auth-enviar').textContent = registro ? 'Cadastrar' : 'Entrar';
	$('auth-alternar').textContent = registro ? 'Já tenho conta' : 'Criar conta';
	$('campo-nome').classList.toggle('oculto', !registro);
	$('auth-nome').required = registro;
	$('auth-dica').classList.toggle('oculto', !registro);
	$('auth-erro').textContent = '';
	$('auth-senha').setAttribute('autocomplete', registro ? 'new-password' : 'current-password');
	const dlg = $('dlg-auth');
	if (!dlg.open) dlg.showModal();
	(registro ? $('auth-nome') : $('auth-email')).focus();
}

async function enviarAuth(ev) {
	ev.preventDefault();
	const campos = ev.target.elements;
	$('auth-erro').textContent = '';
	try {
		if (modoAuth === 'registro') {
			await api('POST', '/auth/register', {
				nome: campos.nome.value, email: campos.email.value, senha: campos.senha.value,
			});
		}
		const usuario = await api('POST', '/auth/login', { email: campos.email.value, senha: campos.senha.value });
		csrfToken = null;
		estado.usuario = usuario;
		await carregarTime();
		ev.target.reset();
		$('dlg-auth').close();
		renderTudo();
		toast(`Bem-vindo, ${usuario.nome}!`);
	} catch (e) {
		$('auth-erro').textContent = textoErro(e);
	}
}

async function sair() {
	try { await api('POST', '/auth/logout'); } catch (_) { /* sessão já encerrada */ }
	estado.usuario = null;
	estado.time = null;
	csrfToken = null;
	renderTudo();
	toast('Você saiu da conta.');
}

/* ---------- Inicialização ---------- */

function ligarEventos() {
	$('form-auth').addEventListener('submit', enviarAuth);
	$('auth-cancelar').addEventListener('click', () => $('dlg-auth').close());
	$('auth-alternar').addEventListener('click', () => abrirAuth(modoAuth === 'login' ? 'registro' : 'login'));
	$('form-pokemon').addEventListener('submit', cadastrarPokemon);

	const select = $('sel-categoria');
	for (const [valor, rotulo] of CATEGORIAS) select.append(el('option', { value: valor }, rotulo));
}

async function iniciar() {
	ligarEventos();
	try {
		await carregarSessao();
		await carregarCatalogo();
	} catch (e) {
		toast(textoErro(e), true);
	}
	renderTudo();
}

document.addEventListener('DOMContentLoaded', iniciar);