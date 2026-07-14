# Frontend smoke tests

## module-smoke.html

Loads the BUILT form-container view module (`build/form-container/view.js`)
in a real browser with a stubbed `@wordpress/interactivity` import and a
minimal form + spam block. Catches module-evaluation errors (TDZ, syntax,
broken imports) that `npm run build` does NOT catch — webpack only checks
syntax, it never executes the module.

Background: 1.4.0 shipped a const declared below the module's init block.
ES modules are deferred, the init ran during evaluation, hit the temporal
dead zone, and the ReferenceError aborted the whole module — spam solver
included, silently rejecting every submission. This file is the test that
would have caught it.

Run after every build, before every deploy:

    npm run build
    python3 -m http.server 8737 &
    open http://localhost:8737/tests/module-smoke.html

Expected on the page/console: `window.__result.loaded === true` and the
hidden spam-solution input fills with a number within ~1 second.
