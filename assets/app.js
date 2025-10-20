// start the Stimulus application
import './bootstrap.js';
import './styles/app.scss';
import 'https://cdn.jsdelivr.net/npm/@tabler/core@1.1.1/dist/js/tabler.min.js';
import 'https://unpkg.com/@dotlottie/player-component@2.7.12/dist/dotlottie-player.mjs';

if (typeof Turbo === "object") {
    Turbo.session.drive = false;
    console.debug("Turbo Drive was disabled. Use explicit %cdata-turbo=\"true\"%c attribute to enable it on a per-element basis.", "font-weight: bolder; color: green;", "font-weight: normal");
}
