# GitHub Pages documentation

This folder contains the full documentation site for **Aicrion\JsonRpc**,
ready to be published with GitHub Pages.

## Publishing

1. Push this repository to GitHub.
2. In your repository settings, go to **Pages**.
3. Under "Build and deployment", choose:
   - Source: **Deploy from a branch**
   - Branch: `main` (or your default branch), folder: `/docs`
4. Save. GitHub will build and publish the site at
   `https://<your-username>.github.io/<repo-name>/`.

## Local preview (optional)

If you have Ruby and Bundler installed:

```bash
cd docs
bundle exec jekyll serve
```

Then open `http://localhost:4000` in your browser.
