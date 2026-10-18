import './bootstrap';

// Turbo intercepts same-origin links and form submissions and swaps the
// page <body> in place instead of doing a full browser reload. Opt a link or
// form out with data-turbo="false".
import '@hotwired/turbo';

import Alpine from 'alpinejs';
import registerDocs from './docs';

window.Alpine = Alpine;

registerDocs(Alpine);

// Alpine watches the whole document for DOM changes, so components in the
// <body> Turbo swaps in are initialized automatically.
Alpine.start();
