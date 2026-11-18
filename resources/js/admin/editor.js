/**
 * Editor soal TipTap (lokal, tanpa CDN) + rumus KaTeX.
 * Rumus ditulis di antara tanda $…$ (inline) atau $$…$$ (blok); dirender KaTeX di pratinjau & ruang ujian.
 * Gambar diunggah lewat Livewire ($wire.upload) → disimpan privat → src = /admin/media/... (di API siswa
 * ditulis ulang menjadi URL bertanda tangan 30 menit).
 *
 * Pemakaian Blade:
 *   <div x-data="richEditor($wire.entangle('content'), 'editorUpload')"> ... </div>  (lihat x-ui.editor)
 */
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import katex from 'katex';

/** Render $…$ dan $$…$$ di dalam elemen menjadi KaTeX (dipakai juga oleh pratinjau). */
export function renderMath(root) {
  if (!root) return;
  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
  const nodes = [];
  while (walker.nextNode()) { if (/\$[^$]+\$/.test(walker.currentNode.nodeValue)) nodes.push(walker.currentNode); }
  nodes.forEach((node) => {
    const frag = document.createDocumentFragment();
    const parts = node.nodeValue.split(/(\$\$[^$]+\$\$|\$[^$]+\$)/g);
    parts.forEach((p) => {
      if (/^\$\$[^$]+\$\$$/.test(p) || /^\$[^$]+\$$/.test(p)) {
        const display = p.startsWith('$$');
        const span = document.createElement('span');
        try { katex.render(p.replace(/^\$\$?|\$\$?$/g, ''), span, { throwOnError: false, displayMode: display }); } catch (e) { span.textContent = p; }
        frag.appendChild(span);
      } else if (p) frag.appendChild(document.createTextNode(p));
    });
    node.parentNode.replaceChild(frag, node);
  });
}

export function registerEditor(Alpine) {
  window.kqRenderMath = renderMath;

  Alpine.data('richEditor', (model, uploadProp = null) => {
    let editor = null; // simpan di luar proxy Alpine (TipTap tidak suka di-proxy)
    return {
      model,
      tick: 0,
      init() {
        editor = new Editor({
          element: this.$refs.editor,
          extensions: [StarterKit.configure({ heading: { levels: [3, 4] } }), Image.configure({ inline: false })],
          content: this.model || '',
          onUpdate: ({ editor: ed }) => { this.model = ed.isEmpty ? '' : ed.getHTML(); },
          onSelectionUpdate: () => { this.tick++; },
          onTransaction: () => { this.tick++; },
        });
        this.$watch('model', (v) => { if (editor && v !== editor.getHTML() && !(editor.isEmpty && !v)) editor.commands.setContent(v || '', false); });
      },
      destroy() { if (editor) editor.destroy(); },
      is(name, attrs) { this.tick; return editor ? editor.isActive(name, attrs) : false; },
      run(cmd) {
        if (!editor) return;
        const c = editor.chain().focus();
        ({
          bold: () => c.toggleBold(), italic: () => c.toggleItalic(), strike: () => c.toggleStrike(),
          bullet: () => c.toggleBulletList(), ordered: () => c.toggleOrderedList(), h3: () => c.toggleHeading({ level: 3 }),
          quote: () => c.toggleBlockquote(), code: () => c.toggleCode(), undo: () => c.undo(), redo: () => c.redo(),
          math: () => c.insertContent(' $x^2$ '),
        }[cmd] || (() => c))().run();
      },
      pickImage() { this.$refs.file && this.$refs.file.click(); },
      upload(e) {
        const file = e.target.files && e.target.files[0];
        if (!file || !uploadProp) return;
        this.$wire.upload(uploadProp, file, () => {
          this.$wire.call('storeEditorImage').then((url) => { if (url && editor) editor.chain().focus().setImage({ src: url }).run(); });
        }, () => window.kqToast && window.kqToast('error', 'Gagal mengunggah gambar'));
        e.target.value = '';
      },
    };
  });

  // Pratinjau: render ulang KaTeX setiap konten berubah.
  Alpine.directive('math', (el, _, { cleanup }) => {
    let t = null;
    const run = () => { clearTimeout(t); t = setTimeout(() => renderMath(el), 30); };
    const mo = new MutationObserver(run);
    mo.observe(el, { childList: true, subtree: true, characterData: true });
    run();
    cleanup(() => mo.disconnect());
  });
}
