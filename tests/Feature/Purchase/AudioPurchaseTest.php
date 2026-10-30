<?php
namespace Tests\Feature\Purchase;
use App\Models\Book;
use Illuminate\Support\Facades\Storage;
class AudioPurchaseTest extends PurchaseTestCase
{
    public function test_admin_can_upload_audio_with_cover_and_description(): void
    {
        Storage::fake('local'); Storage::fake('public');
        $admin = \App\Models\User::factory()->admin()->create();
        $author = \App\Models\Author::factory()->create();
        $payload = ['title' => 'Uploaded Audio', 'type' => 'audio', 'description' => 'An audio story.', 'pricing_type' => 'paid', 'price' => '5000', 'status' => 'published', 'author_ids' => [$author->id]];
        $this->actingAs($admin)->post(route('admin.books.store'), $payload)->assertSessionHasErrors('ebook_file');
        $payload['ebook_file'] = \Illuminate\Http\UploadedFile::fake()->create('story.mp3', 100, 'audio/mpeg');
        $payload['cover_image'] = \Illuminate\Http\UploadedFile::fake()->image('cover.jpg');
        $this->post(route('admin.books.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $book = Book::where('title', 'Uploaded Audio')->firstOrFail();
        $this->assertSame('audio', $book->type());
        $this->assertSame('mp3', $book->file_type);
        $this->assertSame('An audio story.', $book->description);
        Storage::disk('local')->assertExists($book->file_path);
        Storage::disk('public')->assertExists($book->cover_image);
        $this->get(route('books.show', $book))->assertOk()->assertSee('Audio Book');
    }

    public function test_audio_is_downloadable_only_by_its_owner(): void
    {
        Storage::fake('local');
        $owner = $this->customer();
        $book = $this->publishedBook(['title' => 'Audio Story', 'type' => Book::TYPE_AUDIO, 'book_format' => Book::FORMAT_AUDIO, 'file_type' => 'mp3', 'file_path' => 'ebooks/story.mp3']);
        Storage::disk('local')->put($book->file_path, 'ID3 audio content');
        $this->assertSame(Book::TYPE_AUDIO, $book->fresh()->type());
        $this->assertTrue($book->hasDownloadFile());
        $this->assertFalse($book->hasPdfFile());
        $purchase = $this->paidOrder($owner, [['book' => $book]])->purchases()->firstOrFail();
        $this->actingAs($owner)->get(route('account.purchases.download', $purchase))->assertOk()->assertDownload('Audio-Story.mp3');
        $other = $this->customer(['email' => 'audio-other@example.com']);
        $this->actingAs($other)->get(route('account.purchases.download', $purchase))->assertForbidden();
    }
}
