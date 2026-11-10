{{-- Cartão "Documento" fora da Mente: estilo e script (public/js/documento-cartao.js). Incluído uma vez por área,
     logo depois do partials.graficos. --}}
<style>
    .wf-doc { background: #fbfbf8; color: #1b1f24; border-radius: .5rem; padding: 1.1rem; }
    .wf-doc.l-duas { columns: 2; column-gap: 1.25rem; }
    .wf-doc-sec { break-inside: avoid; margin-bottom: .9rem; }
    .wf-doc-sec.cab { background: var(--dc); color: #fff; padding: .8rem; border-radius: .4rem; column-span: all; }
    .wf-doc-t, .wf-doc-x { width: 100%; background: transparent; color: inherit; border: 0; font-family: inherit; border-radius: .25rem; }
    .wf-doc-t { font-weight: 700; font-size: 1rem; padding: .1rem .2rem; border-bottom: 2px solid var(--dc); }
    .wf-doc-sec.cab .wf-doc-t { font-size: 1.4rem; border-color: rgba(255, 255, 255, .35); }
    .wf-doc-x { resize: vertical; padding: .3rem .2rem; font-size: .88rem; line-height: 1.45; }
    .wf-doc-t:focus, .wf-doc-x:focus { outline: none; background: rgba(0, 0, 0, .06); }
    .wf-doc ::placeholder { color: inherit; opacity: .4; }
</style>
<script src="{{ asset('js/documento-cartao.js') }}?v={{ @filemtime(public_path('js/documento-cartao.js')) }}"></script>
