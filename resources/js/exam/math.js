/** Rumus $…$ / $$…$$ → KaTeX (lokal, tanpa CDN) untuk ruang ujian. */
import katex from 'katex';
import 'katex/dist/katex.min.css';

export function renderMath(root) {
  if (!root || !/\$[^$]+\$/.test(root.textContent || '')) return;
  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
  const nodes = [];
  while (walker.nextNode()) { if (/\$[^$]+\$/.test(walker.currentNode.nodeValue)) nodes.push(walker.currentNode); }
  nodes.forEach((node) => {
    const frag = document.createDocumentFragment();
    node.nodeValue.split(/(\$\$[^$]+\$\$|\$[^$]+\$)/g).forEach((p) => {
      if (/^\$\$?[^$]+\$\$?$/.test(p)) {
        const span = document.createElement('span');
        try { katex.render(p.replace(/^\$\$?|\$\$?$/g, ''), span, { throwOnError: false, displayMode: p.startsWith('$$') }); } catch (e) { span.textContent = p; }
        frag.appendChild(span);
      } else if (p) frag.appendChild(document.createTextNode(p));
    });
    node.parentNode.replaceChild(frag, node);
  });
}
