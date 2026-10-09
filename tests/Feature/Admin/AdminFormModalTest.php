<?php

namespace Tests\Feature\Admin;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookChapter;
use App\Models\Category;
use App\Models\User;
use Tests\Feature\Payment\PaymentTestCase;

/**
 * The admin add and edit forms are served twice: as whole pages, and as bare
 * form partials that the shared popup injects.
 *
 * These tests pin both halves of that contract. If the controllers stop
 * returning a partial for XHR requests the popup silently shows an empty box,
 * and if the standalone pages stop rendering the destructive archive control
 * the button is lost from the admin - so both are asserted explicitly.
 */
class AdminFormModalTest extends PaymentTestCase
{
    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_xhr_create_returns_only_the_form_not_a_whole_page(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.books.create'), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();

        $html = $response->getContent();

        $this->assertStringNotContainsString('<!DOCTYPE html>', $html);
        $this->assertStringNotContainsString('sidebar', $html);
        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString(route('admin.books.store'), $html);
    }

    public function test_xhr_edit_returns_only_the_form_and_prefills_the_record(): void
    {
        $book = Book::factory()->create([
            'title' => 'Refactoring Legacy Code',
            'publisher' => 'E-Book Press',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.books.edit', $book), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();

        $html = $response->getContent();

        $this->assertStringNotContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString(route('admin.books.update', $book), $html);
        $this->assertStringContainsString('Refactoring Legacy Code', $html);
        $this->assertStringContainsString('E-Book Press', $html);

        // The update route is a PUT, so the spoofed method field must be there
        // or the save would arrive as a create.
        $this->assertStringContainsString('name="_method"', $html);
        $this->assertStringContainsString('value="PUT"', $html);
    }

    public function test_xhr_form_carries_a_csrf_token(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.authors.create'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString(csrf_token(), $html);
    }

    /**
     * The popup deliberately leaves the archive button out, so a stray Enter
     * key can never archive a record from inside a dialog.
     *
     * The marker is the `confirm()` guard rather than the route: `update` and
     * `destroy` share one URL, since records are bound by slug, so the URL on
     * its own would match the save form and prove nothing.
     */
    public function test_popup_form_hides_the_archive_control_but_the_page_keeps_it(): void
    {
        $admin = $this->admin();
        $book = Book::factory()->create();
        $author = Author::factory()->create();
        $category = Category::factory()->create();

        $popups = [
            'admin.books.edit' => route('admin.books.edit', $book),
            'admin.authors.edit' => route('admin.authors.edit', $author),
            'admin.categories.edit' => route('admin.categories.edit', $category),
        ];

        foreach ($popups as $name => $url) {
            $popup = $this->actingAs($admin)
                ->get($url, ['X-Requested-With' => 'XMLHttpRequest'])
                ->assertOk()
                ->getContent();

            $this->assertStringNotContainsString(
                'return confirm(',
                $popup,
                "{$name} popup still offers the archive button."
            );
        }

        // The standalone pages are untouched and still offer archiving.
        $this->actingAs($admin)
            ->get(route('admin.books.edit', $book))
            ->assertOk()
            ->assertSee('return confirm(', false);
    }

    public function test_standalone_pages_still_render_whole_pages(): void
    {
        $admin = $this->admin();
        $book = Book::factory()->create();
        $author = Author::factory()->create();
        $category = Category::factory()->create();

        foreach ([
            route('admin.books.create'),
            route('admin.books.edit', $book),
            route('admin.authors.create'),
            route('admin.authors.edit', $author),
            route('admin.categories.create'),
            route('admin.categories.edit', $category),
        ] as $url) {
            $this->actingAs($admin)
                ->get($url)
                ->assertOk()
                ->assertSee('<!DOCTYPE html>', false)
                ->assertSee('sidebar', false);
        }
    }

    /**
     * Guards the interaction bug this popup shipped with.
     *
     * The admin theme still carries a pre-Bootstrap dialog, and its
     * `.modal { z-index: 1 }` - being linked after Bootstrap - beat Bootstrap's
     * `.modal { z-index: 1050 }`, sinking the dialog under `.modal-backdrop`
     * (z-index 1040). The modal then looked fine but ate no clicks at all.
     *
     * The assertion is on the effective computed value for the popup itself,
     * since the id selector is what has to outrank the backdrop.
     */
    public function test_the_popup_is_stacked_above_its_own_backdrop(): void
    {
        $bootstrap = (string) file_get_contents(
            public_path('assets/admin/vendor/bootstrap/css/bootstrap.min.css')
        );

        $backdrop = (int) $this->zIndexFor($bootstrap, '.modal-backdrop');
        $this->assertSame(1040, $backdrop, 'Bootstrap backdrop z-index changed; re-check the popup stacking.');

        $style = (string) file_get_contents(public_path('assets/admin/css/style.css'));

        $this->assertGreaterThan(
            $backdrop,
            $this->zIndexFor($style, '#adminFormModal'),
            'The popup is not stacked above .modal-backdrop, so its fields cannot be clicked.'
        );
    }

    /** Pull the z-index declared for a single selector, or 0 if it declares none. */
    private function zIndexFor(string $css, string $selector): int
    {
        $pattern = '/'.preg_quote($selector, '/').'\s*\{([^}]*)\}/';

        if (! preg_match($pattern, $css, $rule)) {
            return 0;
        }

        return preg_match('/z-index\s*:\s*(\d+)/', $rule[1], $value) ? (int) $value[1] : 0;
    }

    /**
     * The popup is meant to be tall and wide, and it only stays that way while
     * the rules that override the legacy dialog survive.
     *
     * The legacy block pins `.modal-content` to 90% of a `modal-lg` dialog and
     * leaves the body with only a max-height, which measured 411px tall and 720px
     * wide on a 746px viewport. These assertions describe the intended floor.
     *
     * The dialog width is declared as a fluid cap - `min(100%, 1000px)` - so it
     * fills a large monitor without ever overflowing a narrow one. The width
     * assertions therefore read the cap out of the min() rather than casting the
     * whole expression to an integer, which is what made this test report a
     * width of 0 for a perfectly valid 1000px cap.
     */
    public function test_the_popup_is_styled_tall_and_wide(): void
    {
        $style = (string) file_get_contents(public_path('assets/admin/css/style.css'));

        $dialog = $this->declarationsFor($style, '#adminFormModal .modal-dialog');
        $body = $this->declarationsFor($style, '#adminFormModal .modal-body');

        $this->assertArrayHasKey('max-width', $dialog, 'The dialog width must stay capped.');

        // Wider than the ~720px the legacy 90% content box used to produce.
        $this->assertGreaterThanOrEqual(720, $this->pixelWidthFor($dialog['max-width']));

        // Fluid rather than a hard 1000px, so a phone or tablet never has to
        // scroll sideways to reach the X button or the footer actions.
        $this->assertStringContainsString('%', $dialog['max-width']);

        // Taller than the 411px the legacy margins used to leave behind.
        $this->assertGreaterThanOrEqual(50, (int) $body['min-height']);

        // A floor, not a hard cap, or a short form would look stunted.
        $this->assertArrayNotHasKey('height', $body);

        // The dialog - not the body - is what must be capped, otherwise the
        // dialog overflows upward and takes the header and X button off screen.
        $this->assertArrayHasKey('max-height', $dialog);
    }

    /**
     * The pixel value declared by a width, understanding both a plain `1200px`
     * and a fluid `min(100%, 1000px)`.
     */
    private function pixelWidthFor(string $value): int
    {
        if (preg_match('/(\d+)\s*px/', $value, $match)) {
            return (int) $match[1];
        }

        return 0;
    }

    /** The declarations inside the first rule for a selector, as a name => value map. */
    private function declarationsFor(string $css, string $selector): array
    {
        preg_match('/'.preg_quote($selector, '/').'\s*\{([^}]*)\}/', $css, $rule);

        $out = [];
        foreach (explode(';', $rule[1] ?? '') as $pair) {
            if (preg_match('/^\s*([a-z-]+)\s*:\s*([^;]+)$/i', $pair, $declaration)) {
                $out[strtolower($declaration[1])] = trim($declaration[2]);
            }
        }

        return $out;
    }

    public function test_every_list_page_offers_its_add_and_edit_forms_as_a_popup(): void
    {
        $admin = $this->admin();
        $book = Book::factory()->create();
        $author = Author::factory()->create();
        $category = Category::factory()->create();

        $targets = [
            route('admin.books.create'),
            route('admin.books.edit', $book),
        ];

        $lists = [
            route('admin.books.index') => $targets,
            route('admin.authors.index') => [route('admin.authors.create'), route('admin.authors.edit', $author)],
            route('admin.categories.index') => [
                route('admin.categories.create'),
                route('admin.categories.edit', $category),
            ],
            route('admin.books.chapters.index', $book) => [
                route('admin.books.chapters.create', $book),
                route('admin.books.chapters.edit', [$book, BookChapter::factory()->create(['book_id' => $book->id])]),
            ],
        ];

        foreach ($lists as $listUrl => $expected) {
            $html = $this->actingAs($admin)->get($listUrl)->assertOk()->getContent();

            foreach ($expected as $formUrl) {
                $this->assertStringContainsString(
                    'data-modal-form="'.$formUrl.'"',
                    $html,
                    "{$listUrl} does not open {$formUrl} in a popup."
                );
            }
        }
    }

    public function test_the_shared_modal_shell_and_script_are_loaded_on_every_admin_page(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.books.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="adminFormModal"', $html);
        $this->assertStringContainsString('assets/admin/js/admin-modal.js', $html);
    }

    /**
     * The chapter toolbar used to be pushed onto the page from inside the form.
     * A pushed block never survives being fetched as a bare partial, so it would
     * have arrived dead inside the popup. It now lives in a real script, loaded
     * on every admin page and called again once the modal injects a form.
     */
    public function test_the_chapter_toolbar_runs_as_a_script_and_not_as_a_pushed_block(): void
    {
        $admin = $this->admin();
        $book = Book::factory()->create();

        $page = $this->actingAs($admin)
            ->get(route('admin.books.chapters.index', $book))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('assets/admin/js/chapter-editor.js', $page);

        $popup = $this->actingAs($admin)
            ->get(route('admin.books.chapters.create', $book), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->getContent();

        // The toolbar markup has to be in the partial for the script to bind to.
        $this->assertStringContainsString('data-chapter-command', $popup);

        // And the partial itself must not try to push anything.
        $this->assertStringNotContainsString('<script', $popup);
    }

    public function test_popups_still_cannot_be_reached_by_non_admins(): void
    {
        $book = Book::factory()->create();
        $customer = $this->customer();

        foreach ([
            route('admin.books.create'),
            route('admin.books.edit', $book),
        ] as $url) {
            $this->actingAs($customer)
                ->get($url, ['X-Requested-With' => 'XMLHttpRequest'])
                ->assertForbidden();
        }
    }

    /**
     * The popup submits with `Accept: application/json`, so a failure has to
     * come back as a 422 error bag the script can paint onto the form.
     */
    public function test_saving_from_a_popup_returns_a_422_error_bag_on_failure(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('admin.authors.store'), ['name' => ''], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $this->assertArrayHasKey('name', $response->json('errors'));
    }

    public function test_saving_from_a_popup_still_creates_and_redirects_on_success(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.authors.store'), ['name' => 'Wanjiru Kamau'], ['Accept' => 'application/json'])
            ->assertRedirect(route('admin.authors.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('authors', ['name' => 'Wanjiru Kamau']);
    }

    public function test_updating_from_a_popup_still_updates_the_record(): void
    {
        $author = Author::factory()->create(['name' => 'Old Name']);

        $this->actingAs($this->admin())
            ->put(route('admin.authors.update', $author), ['name' => 'New Name'], ['Accept' => 'application/json'])
            ->assertRedirect(route('admin.authors.index'));

        $this->assertDatabaseHas('authors', ['id' => $author->id, 'name' => 'New Name']);
    }

    /**
     * Chapters are nested under their book, and the popup must not become a way
     * around that check.
     */
    public function test_chapter_popup_cannot_edit_a_chapter_through_the_wrong_book(): void
    {
        $admin = $this->admin();
        $bookA = Book::factory()->create();
        $bookB = Book::factory()->create();
        $chapter = BookChapter::factory()->create(['book_id' => $bookA->id]);

        $this->actingAs($admin)
            ->get(route('admin.books.chapters.edit', [$bookB, $chapter]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertNotFound();
    }
}
