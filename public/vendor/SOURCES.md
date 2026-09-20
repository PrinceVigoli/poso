Locally served dependencies used by the Blade layouts:

- Bootstrap 5.3.0: https://github.com/twbs/bootstrap/tree/v5.3.0 — MIT.
- Bootstrap Icons 1.11.0: https://github.com/twbs/icons/tree/v1.11.0 — MIT. Only the WOFF2 font is referenced.
- Inter and Source Serif 4: obtained from Google Fonts; SIL Open Font License. Fonts are served locally with font-display: swap.

License files accompany the assets. Updating a dependency means replacing its files and license together, running `npm run build`, and checking the rendered interfaces. The browser does not require a CDN connection.
