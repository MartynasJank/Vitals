import hljs from 'highlight.js/lib/core';
import nginx from 'highlight.js/lib/languages/nginx';
import bash from 'highlight.js/lib/languages/bash';

hljs.registerLanguage('nginx', nginx);
hljs.registerLanguage('bash', bash);

window.hljs = hljs;
