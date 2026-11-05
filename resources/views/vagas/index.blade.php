<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Vagas Work Flower</title>
<style>body{margin:0}</style>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700&family=DM+Sans:wght@400;500;600&family=DM+Mono:wght@400;500&display=swap">
<style>
  /* Layout: cabeçalho da vaga em cima; abaixo, formulário à esquerda e aba lateral "Recomendação da empresa" à direita (empilha no celular). */
  :root {
    /* dark-first, como o painel Work Flower */
    --bg: #101612;
    --surface: #171f1a;
    --surface-2: #1e2822;
    --line: #2c3931;
    --fg: #ebf1ed;
    --muted: #9baba1;
    --accent: #f08a9b;
    --accent-ink: #2b0f16;
    --leaf: #8fd1a0;
    --danger: #ff9b8f;
    --font-display: "Bricolage Grotesque", "Trebuchet MS", system-ui, sans-serif;
    --font-body: "DM Sans", system-ui, -apple-system, "Segoe UI", sans-serif;
    --font-mono: "DM Mono", ui-monospace, "SFMono-Regular", Menlo, monospace;
    color-scheme: dark;
  }
  @media (prefers-color-scheme: light) {
    :root:not([data-theme="dark"]) {
      --bg: #f2f6f3; --surface: #ffffff; --surface-2: #e8efea; --line: #cfdad2;
      --fg: #13201a; --muted: #55675c; --accent: #bf3f58; --accent-ink: #ffffff;
      --leaf: #2b7a47; --danger: #b3321f; color-scheme: light;
    }
  }
  :root[data-theme="light"] {
    --bg: #f2f6f3; --surface: #ffffff; --surface-2: #e8efea; --line: #cfdad2;
    --fg: #13201a; --muted: #55675c; --accent: #bf3f58; --accent-ink: #ffffff;
    --leaf: #2b7a47; --danger: #b3321f; color-scheme: light;
  }

  * { box-sizing: border-box; }
  body {
    background: var(--bg);
    color: var(--fg);
    font-family: var(--font-body);
    font-size: 16px;
    line-height: 1.55;
    padding-block: 28px 56px;
    padding-left: max(16px, env(safe-area-inset-left, 0px));
    padding-right: max(16px, env(safe-area-inset-right, 0px));
  }
  .wrap { max-width: 1120px; margin: 0 auto; display: grid; gap: 28px; }
  h1, h2, h3 { font-family: var(--font-display); font-weight: 700; line-height: 1.12; margin: 0; text-wrap: balance; }
  p { margin: 0; }
  .mono { font-family: var(--font-mono); }
  .label { font-family: var(--font-mono); font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); }

  /* topo */
  .top { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
  .brand { display: flex; align-items: center; gap: 10px; font-family: var(--font-display); font-weight: 700; font-size: 20px; }
  .brand svg { width: 30px; height: 30px; }
  .open-pill { display: inline-flex; align-items: center; gap: 8px; border: 1px solid var(--line); border-radius: 999px; padding: 5px 12px; font-size: 13px; color: var(--muted); }
  .open-pill i { width: 8px; height: 8px; border-radius: 50%; background: var(--leaf); }

  /* vaga */
  .job { display: grid; gap: 22px; padding-block: 8px 4px; }
  .job h1 { font-size: clamp(2rem, 5.2vw, 3.4rem); max-width: 18ch; }
  .job h1 em { font-style: normal; color: var(--accent); }
  .job .lead { max-width: 62ch; color: var(--muted); font-size: 1.06rem; }
  .facts { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); border-block: 1px solid var(--line); margin: 0; }
  .facts div { padding: 14px 16px 14px 0; display: grid; gap: 2px; min-width: 0; }
  .facts div + div { padding-left: 16px; border-left: 1px solid var(--line); }
  .facts dt { font-family: var(--font-mono); font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); }
  .facts dd { margin: 0; font-weight: 600; }
  .job-cols { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 28px; }
  .job-cols h2 { font-size: 1.15rem; margin-bottom: 8px; }
  .job-cols p, .job-cols li { color: var(--muted); }
  .job-cols ul { margin: 0; padding-left: 1.1em; display: grid; gap: 4px; }

  /* formulário + aba */
  .main { display: grid; grid-template-columns: minmax(0, 1fr) 380px; gap: 28px; align-items: start; }
  .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 26px; min-width: 0; }
  .panel > h2 { font-size: 1.5rem; margin-bottom: 4px; }
  .panel > .sub { color: var(--muted); margin-bottom: 22px; }
  form { display: grid; gap: 18px; }
  .row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
  .field { display: grid; gap: 6px; min-width: 0; }
  .field > label, .field > .legend { font-size: 14px; font-weight: 600; }
  input[type="text"], input[type="email"], input[type="tel"], select, textarea {
    width: 100%; font: inherit; color: var(--fg); background: var(--surface-2);
    border: 1px solid var(--line); border-radius: 8px; padding: 11px 12px; min-height: 44px;
  }
  textarea { resize: vertical; min-height: 92px; }
  input:focus-visible, select:focus-visible, textarea:focus-visible, button:focus-visible, summary:focus-visible, .chip input:focus-visible + span {
    outline: 2px solid var(--accent); outline-offset: 2px;
  }
  .field[data-invalid] input, .field[data-invalid] select, .field[data-invalid] textarea { border-color: var(--danger); }
  .err { color: var(--danger); font-size: 13px; min-height: 0; }
  .err:empty { display: none; }
  fieldset { border: 0; padding: 0; margin: 0; min-width: 0; }
  .days { display: flex; flex-wrap: wrap; gap: 8px; }
  .chip { position: relative; }
  .chip input { position: absolute; opacity: 0; inset: 0; margin: 0; cursor: pointer; }
  .chip span { display: inline-flex; align-items: center; justify-content: center; min-width: 52px; min-height: 40px; padding: 0 12px; border: 1px solid var(--line); border-radius: 8px; background: var(--surface-2); font-family: var(--font-mono); font-size: 13px; cursor: pointer; }
  .chip input:checked + span { background: var(--accent); border-color: var(--accent); color: var(--accent-ink); font-weight: 500; }
  .range-wrap { display: grid; gap: 6px; }
  .range-top { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; }
  .range-top output { font-family: var(--font-display); font-size: 1.4rem; font-weight: 700; color: var(--accent); }
  input[type="range"] { width: 100%; accent-color: var(--accent); min-height: 32px; }
  .scale { display: flex; justify-content: space-between; font-family: var(--font-mono); font-size: 12px; color: var(--muted); }
  .check { display: grid; grid-template-columns: 22px minmax(0, 1fr); gap: 10px; align-items: start; font-size: 14px; }
  .check input { width: 20px; height: 20px; margin: 2px 0 0; accent-color: var(--accent); }
  .check.invalid { color: var(--danger); }
  .btn { font: inherit; font-weight: 600; border: 0; border-radius: 10px; padding: 0 22px; min-height: 48px; cursor: pointer; background: var(--accent); color: var(--accent-ink); }
  .btn:hover { filter: brightness(1.06); }
  .btn.ghost { background: transparent; color: var(--fg); border: 1px solid var(--line); }
  .note { font-size: 13px; color: var(--muted); }

  /* confirmação */
  .done h2 { font-size: 1.5rem; }
  .done .sub { color: var(--muted); margin: 4px 0 20px; }
  .summary { display: grid; gap: 0; margin: 0 0 22px; border-top: 1px solid var(--line); }
  .summary div { display: grid; grid-template-columns: 150px minmax(0, 1fr); gap: 12px; padding: 11px 0; border-bottom: 1px solid var(--line); }
  .summary dt { font-family: var(--font-mono); font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); padding-top: 3px; }
  .summary dd { margin: 0; overflow-wrap: anywhere; }

  /* aba lateral */
  .side { position: sticky; top: 16px; display: grid; gap: 0; }
  .tab-head { display: flex; align-items: center; gap: 10px; padding: 12px 18px; border: 1px solid var(--line); border-bottom: 0; border-radius: 14px 14px 0 0; background: var(--surface-2); font-family: var(--font-mono); font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); }
  .tab-head i { width: 8px; height: 8px; border-radius: 50%; background: var(--line); }
  .side[data-open] .tab-head { color: var(--fg); }
  .side[data-open] .tab-head i { background: var(--accent); }
  .tab-body { background: var(--surface); border: 1px solid var(--line); border-radius: 0 0 14px 14px; padding: 22px; display: grid; gap: 16px; }
  .pending p { color: var(--muted); }
  .pending .ghostlines { display: grid; gap: 8px; margin-top: 4px; }
  .pending .ghostlines span { height: 10px; border-radius: 6px; background: var(--surface-2); }
  .pending .ghostlines span:nth-child(2) { width: 82%; }
  .pending .ghostlines span:nth-child(3) { width: 58%; }
  .course h3 { font-size: 1.45rem; }
  .course .meta { font-family: var(--font-mono); font-size: 13px; color: var(--muted); }
  .course .hello { color: var(--muted); }
  .highlight { border-left: 3px solid var(--accent); padding: 4px 0 4px 14px; font-weight: 600; }
  .fit { display: flex; align-items: baseline; gap: 12px; padding: 12px 14px; background: var(--surface-2); border-radius: 10px; }
  .fit b { font-family: var(--font-display); font-size: 1.6rem; color: var(--leaf); white-space: nowrap; }
  .fit span { font-size: 14px; color: var(--muted); }
  details { border-top: 1px solid var(--line); padding-top: 12px; }
  summary { cursor: pointer; font-weight: 600; list-style: none; display: flex; justify-content: space-between; gap: 10px; }
  summary::-webkit-details-marker { display: none; }
  summary::after { content: "+"; font-family: var(--font-mono); color: var(--accent); }
  details[open] summary::after { content: "−"; }
  .weeks { list-style: none; margin: 12px 0 0; padding: 0; display: grid; gap: 10px; }
  .weeks li { display: grid; grid-template-columns: 74px minmax(0, 1fr); gap: 10px; font-size: 14px; }
  .weeks b { font-family: var(--font-mono); font-weight: 500; font-size: 12px; letter-spacing: .06em; text-transform: uppercase; color: var(--accent); padding-top: 2px; }
  .optional { font-size: 13px; color: var(--muted); border-top: 1px solid var(--line); padding-top: 12px; }

  a.btn { display: inline-flex; align-items: center; justify-content: center; text-decoration: none; }
  .course h3 a { color: inherit; text-decoration: none; }
  .course h3 a:hover { text-decoration: underline; text-decoration-color: var(--accent); text-underline-offset: 4px; }
  .side[data-open] .tab-head { cursor: pointer; }
  .side[data-open] .tab-head::after { content: "↗"; margin-left: auto; color: var(--accent); }
  .hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
  footer { color: var(--muted); font-size: 13px; border-top: 1px solid var(--line); padding-top: 16px; }

  @media (prefers-reduced-motion: no-preference) {
    .reveal { animation: rise .45s ease both; }
    @keyframes rise { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
  }
  @media (max-width: 920px) {
    .main { grid-template-columns: minmax(0, 1fr); }
    .side { position: static; }
  }
  @media (max-width: 640px) {
    .facts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .facts div:nth-child(3) { padding-left: 0; border-left: 0; }
    .facts div:nth-child(n+3) { border-top: 1px solid var(--line); }
    .job-cols, .row { grid-template-columns: minmax(0, 1fr); }
    .panel { padding: 20px 16px; }
    .summary div { grid-template-columns: minmax(0, 1fr); gap: 2px; }
  }
</style>
</head>
<body>
<div class="wrap">
  <header class="top">
    <div class="brand">
      <svg viewBox="0 0 40 40" aria-hidden="true">
        <g fill="var(--accent)">
          <ellipse cx="20" cy="10" rx="6" ry="9"/>
          <ellipse cx="20" cy="10" rx="6" ry="9" transform="rotate(72 20 20)"/>
          <ellipse cx="20" cy="10" rx="6" ry="9" transform="rotate(144 20 20)"/>
          <ellipse cx="20" cy="10" rx="6" ry="9" transform="rotate(216 20 20)"/>
          <ellipse cx="20" cy="10" rx="6" ry="9" transform="rotate(288 20 20)"/>
        </g>
        <circle cx="20" cy="20" r="5" fill="var(--bg)"/>
      </svg>
      Work Flower
    </div>
    <span class="open-pill"><i></i> Vaga aberta</span>
  </header>

  <section class="job" aria-labelledby="vaga-titulo">
    <h1 id="vaga-titulo">Trabalhe de casa e receba por <em>estar disponível</em></h1>
    <p class="lead">A Work Flower organiza a rotina de pessoas e equipes em quatro áreas: Corpo, Mente, Carteira e Diverso. Estamos contratando assistentes de organização para atuar em home-office, em meio período.</p>
    <dl class="facts">
      <div><dt>Modalidade</dt><dd>Home-office</dd></div>
      <div><dt>Jornada</dt><dd>Meio período</dd></div>
      <div><dt>Pagamento</dt><dd>Por disponibilidade</dd></div>
      <div><dt>Local</dt><dd>100% remoto</dd></div>
    </dl>
    <div class="job-cols">
      <div>
        <h2>Como funciona o pagamento</h2>
        <p>Você informa os dias e horários em que fica disponível. A Work Flower paga por essas horas, e as tarefas chegam dentro da janela combinada. O valor por hora é apresentado na entrevista.</p>
      </div>
      <div>
        <h2>O que pedimos</h2>
        <ul>
          <li>Computador e internet estável em casa</li>
          <li>Escrita clara e retorno dentro do horário combinado</li>
          <li>Cuidado com prazos e com organização de arquivos</li>
        </ul>
      </div>
    </div>
  </section>

  <div class="main">
    <section class="panel" aria-labelledby="form-titulo">
      <div id="formArea">
        <h2 id="form-titulo">Candidate-se</h2>
        <p class="sub">Leva cerca de três minutos. Candidatar-se não tem custo.</p>

        <form id="applyForm" novalidate data-action="{{ route('vagas.store') }}">
          <div class="hp" aria-hidden="true"><label>Não preencha <input type="text" name="site" id="site" tabindex="-1" autocomplete="off"></label></div>
          <div class="row">
            <div class="field" id="f-nome">
              <label for="nome">Nome completo</label>
              <input type="text" id="nome" name="nome" autocomplete="name" aria-describedby="e-nome">
              <div class="err" id="e-nome"></div>
            </div>
            <div class="field" id="f-email">
              <label for="email">E-mail</label>
              <input type="email" id="email" name="email" autocomplete="email" aria-describedby="e-email">
              <div class="err" id="e-email"></div>
            </div>
          </div>

          <div class="row">
            <div class="field" id="f-fone">
              <label for="fone">WhatsApp</label>
              <input type="tel" id="fone" name="fone" autocomplete="tel" placeholder="(77) 90000-0000" aria-describedby="e-fone">
              <div class="err" id="e-fone"></div>
            </div>
            <div class="field" id="f-cidade">
              <label for="cidade">Cidade e estado</label>
              <input type="text" id="cidade" name="cidade" autocomplete="address-level2" placeholder="Cidade - UF" aria-describedby="e-cidade">
              <div class="err" id="e-cidade"></div>
            </div>
          </div>

          <div class="row">
            <div class="field" id="f-escolaridade">
              <label for="escolaridade">Escolaridade</label>
              <select id="escolaridade" name="escolaridade" aria-describedby="e-escolaridade">
                <option value="">Selecione</option>
                <option>Ensino médio completo</option>
                <option>Ensino técnico</option>
                <option>Superior em andamento</option>
                <option>Superior completo</option>
              </select>
              <div class="err" id="e-escolaridade"></div>
            </div>
            <div class="field" id="f-exp">
              <label for="exp">Experiência com trabalho remoto</label>
              <select id="exp" name="exp" aria-describedby="e-exp">
                <option value="">Selecione</option>
                <option>Nenhuma ainda</option>
                <option>Menos de 1 ano</option>
                <option>De 1 a 3 anos</option>
                <option>Mais de 3 anos</option>
              </select>
              <div class="err" id="e-exp"></div>
            </div>
          </div>

          <fieldset class="field" id="f-dias">
            <legend class="legend" style="font-size:14px;font-weight:600;padding:0;margin-bottom:6px">Dias em que você fica disponível</legend>
            <div class="days" role="group" aria-describedby="e-dias">
              <label class="chip"><input type="checkbox" name="dias" value="Seg"><span>Seg</span></label>
              <label class="chip"><input type="checkbox" name="dias" value="Ter"><span>Ter</span></label>
              <label class="chip"><input type="checkbox" name="dias" value="Qua"><span>Qua</span></label>
              <label class="chip"><input type="checkbox" name="dias" value="Qui"><span>Qui</span></label>
              <label class="chip"><input type="checkbox" name="dias" value="Sex"><span>Sex</span></label>
              <label class="chip"><input type="checkbox" name="dias" value="Sáb"><span>Sáb</span></label>
              <label class="chip"><input type="checkbox" name="dias" value="Dom"><span>Dom</span></label>
            </div>
            <div class="err" id="e-dias"></div>
          </fieldset>

          <div class="row">
            <div class="field" id="f-turno">
              <label for="turno">Melhor período do dia</label>
              <select id="turno" name="turno" aria-describedby="e-turno">
                <option value="">Selecione</option>
                <option>Manhã (6h às 12h)</option>
                <option>Tarde (12h às 18h)</option>
                <option>Noite (18h às 23h)</option>
                <option>Varia conforme o dia</option>
              </select>
              <div class="err" id="e-turno"></div>
            </div>
            <div class="field range-wrap">
              <div class="range-top">
                <label for="horas" style="font-size:14px;font-weight:600">Horas disponíveis por semana</label>
                <output id="horasOut" for="horas">15 h</output>
              </div>
              <input type="range" id="horas" name="horas" min="10" max="25" step="1" value="15">
              <div class="scale"><span>10 h</span><span>25 h</span></div>
            </div>
          </div>

          <div class="field">
            <label for="msg">Quer contar algo sobre você? (opcional)</label>
            <textarea id="msg" name="msg" placeholder="Experiências, ferramentas que você usa, o que gosta de organizar"></textarea>
          </div>

          <div class="field" id="f-equip">
            <label class="check" for="equip"><input type="checkbox" id="equip" name="equip"><span>Tenho computador e internet estável para trabalhar de casa.</span></label>
            <div class="err" id="e-equip"></div>
          </div>
          <div class="field" id="f-lgpd">
            <label class="check" for="lgpd"><input type="checkbox" id="lgpd" name="lgpd"><span>Autorizo a Work Flower a usar estes dados apenas para analisar minha candidatura.</span></label>
            <div class="err" id="e-lgpd"></div>
          </div>

          <div>
            <button type="submit" class="btn" id="submitBtn">Enviar candidatura</button>
          </div>
          <div class="err" id="e-geral" role="alert"></div>
          <p class="note">Não pedimos CPF, documentos nem dados bancários nesta etapa.</p>
        </form>
      </div>

      <div id="doneArea" class="done" hidden>
        <h2 id="doneTitle" tabindex="-1">Candidatura registrada</h2>
        <p class="sub" id="doneSub"></p>
        <dl class="summary" id="summary"></dl>
        <button type="button" class="btn ghost" id="editBtn">Editar respostas</button>
      </div>
    </section>

    <aside class="side" id="side" aria-label="Recomendação da empresa">
      <div class="tab-head"><i></i><span>Recomendação da empresa</span></div>
      <div class="tab-body" aria-live="polite">
        <div class="pending" id="pending">
          <p>Depois que você enviar seus dados, a Work Flower mostra aqui o curso que recomenda para quem quer aumentar as chances de contratação.</p>
          <div class="ghostlines" aria-hidden="true"><span></span><span></span><span></span></div>
        </div>

        <div class="course" id="course" hidden>
          <div class="label">Curso recomendado</div>
          <h3><a id="cursoTitulo" href="#" target="_blank" rel="noopener">Organização Digital para Trabalho Remoto</a></h3>
          <div class="meta">Online · 4 semanas · 20 horas</div>
          <p class="hello" id="hello"></p>
          <p class="highlight">Candidatos que fizeram este curso costumam ser contratados com mais frequência.</p>
          <div class="fit"><b>5 h/sem</b><span id="fitText"></span></div>
          <a class="btn" id="cursoBtn" href="#" target="_blank" rel="noopener">Acessar o curso</a>
          <details>
            <summary>O que o curso ensina</summary>
            <ol class="weeks">
              <li><b>Semana 1</b><span>Áreas da rotina: Corpo, Mente, Carteira e Diverso</span></li>
              <li><b>Semana 2</b><span>Tarefas, prioridades e blocos de horário</span></li>
              <li><b>Semana 3</b><span>Comunicação por escrito e registro de disponibilidade</span></li>
              <li><b>Semana 4</b><span>Arquivos, planilhas e entrega de relatórios</span></li>
            </ol>
          </details>
          <p class="optional">O curso é opcional. Sua candidatura é analisada da mesma forma com ou sem ele.</p>
        </div>
      </div>
    </aside>
  </div>

  <footer>Os dados enviados neste formulário são usados apenas para analisar sua candidatura à vaga da Work Flower.</footer>
</div>

<script>
(function () {
  var $ = function (id) { return document.getElementById(id); };
  var form = $('applyForm');
  var horas = $('horas');
  var CAMPOS = ['nome','email','fone','cidade','escolaridade','exp','dias','turno','equip','lgpd'];
  var codigo = null, cursoUrl = null, enviando = false;
  horas.addEventListener('input', function () { $('horasOut').textContent = horas.value + ' h'; });

  function setErr(key, msg) {
    var box = $('f-' + key), err = $('e-' + key);
    if (!box || !err) return;
    err.textContent = msg || '';
    if (msg) box.setAttribute('data-invalid', ''); else box.removeAttribute('data-invalid');
  }
  function clearAll() { CAMPOS.forEach(function (k) { setErr(k, ''); }); $('e-geral').textContent = ''; }

  function validate() {
    clearAll();
    var bad = [];
    function need(key, cond, msg) { if (!cond) { setErr(key, msg); bad.push(key); } }
    var nome = $('nome').value.trim();
    need('nome', nome.split(/\s+/).filter(Boolean).length >= 2, 'Digite nome e sobrenome.');
    need('email', /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test($('email').value.trim()), 'Digite um e-mail válido, como nome@exemplo.com.');
    need('fone', $('fone').value.replace(/\D/g, '').length >= 10, 'Digite o WhatsApp com DDD.');
    need('cidade', $('cidade').value.trim().length >= 3, 'Informe sua cidade e estado.');
    need('escolaridade', $('escolaridade').value !== '', 'Selecione sua escolaridade.');
    need('exp', $('exp').value !== '', 'Selecione uma opção.');
    need('dias', form.querySelectorAll('input[name="dias"]:checked').length > 0, 'Marque pelo menos um dia.');
    need('turno', $('turno').value !== '', 'Selecione o melhor período.');
    need('equip', $('equip').checked, 'Esta vaga exige computador e internet estável.');
    need('lgpd', $('lgpd').checked, 'Sem esta autorização não podemos analisar a candidatura.');
    return bad;
  }

  function focusField(key) {
    var box = $('f-' + key);
    if (!box) return;
    var target = box.querySelector('input, select, textarea');
    if (target) target.focus();
    box.scrollIntoView({ block: 'center', behavior: 'smooth' });
  }

  function el(tag, text) { var e = document.createElement(tag); e.textContent = text; return e; }

  function abrirCurso() { if (cursoUrl) window.open(cursoUrl, '_blank', 'noopener'); }

  function mostrarConfirmacao() {
    var nome = $('nome').value.trim();
    var primeiro = nome.split(/\s+/)[0];
    var dias = Array.prototype.map.call(form.querySelectorAll('input[name="dias"]:checked'), function (c) { return c.value; });
    var h = parseInt(horas.value, 10);

    $('doneSub').textContent = 'Obrigado, ' + primeiro + '. Recebemos sua candidatura. Confira abaixo o que você informou.';
    var s = $('summary');
    s.textContent = '';
    [
      ['Nome', nome],
      ['E-mail', $('email').value.trim()],
      ['WhatsApp', $('fone').value.trim()],
      ['Cidade', $('cidade').value.trim()],
      ['Escolaridade', $('escolaridade').value],
      ['Experiência remota', $('exp').value],
      ['Disponibilidade', dias.join(', ') + ' · ' + $('turno').value + ' · ' + h + ' h por semana']
    ].forEach(function (p) {
      var d = document.createElement('div');
      d.appendChild(el('dt', p[0]));
      d.appendChild(el('dd', p[1]));
      s.appendChild(d);
    });

    $('hello').textContent = primeiro + ', para quem quer se preparar para a rotina da vaga, a Work Flower recomenda este curso.';
    $('fitText').textContent = 'Você indicou ' + h + ' h por semana. O curso ocupa cerca de 5 h por semana, então ' +
      (h >= 15 ? 'cabe com folga na sua disponibilidade.' : 'cabe na sua disponibilidade, usando boa parte dela.');

    // A aba do curso (título, botão e cabeçalho) redireciona para o link do curso.
    $('cursoBtn').href = cursoUrl || '#';
    $('cursoTitulo').href = cursoUrl || '#';

    $('formArea').hidden = true;
    $('doneArea').hidden = false;
    $('pending').hidden = true;
    var c = $('course');
    c.hidden = false;
    c.classList.remove('reveal'); void c.offsetWidth; c.classList.add('reveal');
    $('side').setAttribute('data-open', '');
    $('doneTitle').focus({ preventScroll: true });
    var narrow = window.matchMedia('(max-width: 920px)').matches;
    (narrow ? $('side') : $('doneArea')).scrollIntoView({ block: 'start', behavior: 'smooth' });
  }

  // Cabeçalho da aba também leva ao curso depois do envio.
  var tabHead = document.querySelector('#side .tab-head');
  tabHead.addEventListener('click', function () { if ($('side').hasAttribute('data-open')) abrirCurso(); });
  tabHead.addEventListener('keydown', function (ev) {
    if ((ev.key === 'Enter' || ev.key === ' ') && $('side').hasAttribute('data-open')) { ev.preventDefault(); abrirCurso(); }
  });
  new MutationObserver(function () {
    var aberto = $('side').hasAttribute('data-open');
    if (aberto) { tabHead.setAttribute('role', 'link'); tabHead.setAttribute('tabindex', '0'); }
  }).observe($('side'), { attributes: true, attributeFilter: ['data-open'] });

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    if (enviando) return;
    var bad = validate();
    if (bad.length) { focusField(bad[0]); return; }

    var payload = {
      codigo: codigo,
      site: $('site').value,
      nome: $('nome').value.trim(),
      email: $('email').value.trim(),
      fone: $('fone').value.trim(),
      cidade: $('cidade').value.trim(),
      escolaridade: $('escolaridade').value,
      exp: $('exp').value,
      dias: Array.prototype.map.call(form.querySelectorAll('input[name="dias"]:checked'), function (c) { return c.value; }),
      turno: $('turno').value,
      horas: parseInt(horas.value, 10),
      msg: $('msg').value.trim(),
      equip: $('equip').checked,
      lgpd: $('lgpd').checked
    };

    var btn = $('submitBtn');
    enviando = true; btn.disabled = true; btn.textContent = 'Enviando…';

    fetch(form.getAttribute('data-action'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify(payload)
    })
    .then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (d) { return { status: r.status, data: d }; });
    })
    .then(function (res) {
      if (res.status === 422 && res.data.errors) {
        var first = null;
        Object.keys(res.data.errors).forEach(function (k) {
          var key = k.split('.')[0];
          if (CAMPOS.indexOf(key) === -1) return;
          setErr(key, res.data.errors[k][0]);
          if (!first) first = key;
        });
        if (first) focusField(first); else $('e-geral').textContent = 'Confira os dados e tente de novo.';
        return;
      }
      if (res.status === 419) { $('e-geral').textContent = 'Sua sessão expirou. Recarregue a página e envie de novo.'; return; }
      if (res.status === 429) { $('e-geral').textContent = 'Muitas tentativas seguidas. Aguarde um minuto e tente de novo.'; return; }
      if (!res.data || !res.data.ok) { $('e-geral').textContent = 'Não foi possível enviar agora. Tente de novo em instantes.'; return; }
      codigo = res.data.codigo;
      cursoUrl = res.data.curso_url;
      mostrarConfirmacao();
    })
    .catch(function () { $('e-geral').textContent = 'Não foi possível enviar. Verifique sua conexão e tente de novo.'; })
    .then(function () { enviando = false; btn.disabled = false; btn.textContent = 'Enviar candidatura'; });
  });

  $('editBtn').addEventListener('click', function () {
    $('doneArea').hidden = true;
    $('formArea').hidden = false;
    $('nome').focus();
  });
})();
</script>
</body>
</html>
