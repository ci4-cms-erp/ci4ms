# Contributing to ci4ms

Thank you for considering contributing to **ci4ms – CodeIgniter 4 Modular CMS/ERP**!  
This guide outlines how you can get started, contribute effectively, and follow the best practices when working with this project.

---

## 🚀 Getting Started

Before contributing, please ensure you have:

- Read the [README](./README.md) to understand the scope and purpose of the project.
- Installed dependencies and set up a CodeIgniter 4 environment.
- Familiarized yourself with the modular directory structure and CodeIgniter Shield integration.
- Enabled development mode (`CI_ENVIRONMENT = development`) for better debugging.

---

## 🧑‍💻 How to Contribute

You can help improve **ci4ms** in several ways:

- Reporting bugs
- Suggesting new features or module ideas
- Improving documentation
- Contributing code via pull requests

---

## 🐛 Reporting Bugs

To report a bug:

1. Search the [Issues](https://github.com/ci4-cms-erp/ci4ms/issues) tab to see if it’s already known.
2. If not listed, open a new issue using the **Bug Report** template.
3. Provide detailed information:
   - Steps to reproduce the issue
   - Expected vs. actual results
   - PHP and CodeIgniter versions
   - Any relevant logs or stack traces

---

## ✨ Suggesting Enhancements

Have a feature or improvement in mind?

1. Open an issue with the **Feature Request** template.
2. Describe the need and proposed solution clearly.
3. Explain how it fits into the modular structure of the CMS/ERP.

---

## 📂 Project Structure Notes

- **ci4ms** follows a modular folder structure (e.g., `Modules/Auth`, `Modules/Users`, `Modules/Settings`).
- Avoid hard-coded paths; use service locators and helpers when possible.
- Use **PSR-12** coding standards.
- Keep logic reusable, secure, and clean. Each module should be self-contained.

---

## 📥 Submitting a Pull Request

1. Fork this repository.
2. Create a new feature branch:
   ```bash
   git checkout -b feature/your-feature-name
   ```
3. Add your changes in the appropriate module.
4. Run any tests and ensure no errors exist.
5. Commit with a clear message:
   ```bash
   git commit -m "Add [Module]: Short description of feature"
   ```
6. Push to your fork:
   ```bash
   git push origin feature/your-feature-name
   ```
7. Open a Pull Request and describe your changes and reasoning.

---

## ✅ Pull Request Requirements

- Code **must not break existing functionality**.
- Follow modular conventions and naming consistency.
- Add or update tests if applicable.
- Update README or related module documentation if needed.

---

## 🧪 Testing

- Run module-specific tests (if present).
- Aim for small, clearly scoped commits for easier reviews.
- If applicable, use `php spark test` or integrate PHPUnit directly.

---

## 🗣 Code of Conduct

We expect contributors to follow the [Code of Conduct](./CODE_OF_CONDUCT.md) in all interactions.

---

## 🙏 Thank You

Your support makes **ci4ms** better!  
Every issue you report, feature you suggest, or code you contribute helps the community grow 💙
