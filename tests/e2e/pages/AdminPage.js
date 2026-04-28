/**
 * Base page object for Ash-Nazg admin pages.
 */
class AdminPage {
  constructor(page) {
    this.page = page;
  }

  /** Branded Pantheon header should appear on every plugin page. */
  get header() {
    return this.page.locator('.ash-nazg-header');
  }

  /** Page title (h1 inside the wrap). */
  get pageTitle() {
    return this.page.locator('.wrap h1, .wrap h2').first();
  }

  /** Any visible admin notice. */
  get notices() {
    return this.page.locator('.notice');
  }

  async hasNoErrors() {
    const errorNotices = this.page.locator('.notice-error');
    return (await errorNotices.count()) === 0;
  }
}

module.exports = { AdminPage };
