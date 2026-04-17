<?php

namespace Tests\Feature;

use App\Models\GalnetNews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalnetNewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_paginated_articles(): void
    {
        GalnetNews::factory()->count(3)->create();

        $response = $this->getJson('/api/galnet/news');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'title', 'content', 'audio_file', 'uploaded_at', 'banner_image', 'slug'],
            ],
        ]);
    }

    public function test_index_respects_limit_parameter(): void
    {
        GalnetNews::factory()->count(5)->create();

        $response = $this->getJson('/api/galnet/news?limit=2');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_index_returns_articles_ordered_by_most_recent(): void
    {
        $older = GalnetNews::factory()->create(['order_added' => 1]);
        $newer = GalnetNews::factory()->create(['order_added' => 100]);

        $response = $this->getJson('/api/galnet/news');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $newer->id);
        $response->assertJsonPath('data.1.id', $older->id);
    }

    public function test_index_returns_empty_data_when_no_articles_exist(): void
    {
        $response = $this->getJson('/api/galnet/news');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_show_returns_article_by_slug(): void
    {
        $article = GalnetNews::factory()->create();

        $response = $this->getJson("/api/galnet/news/{$article->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.title', $article->title);
        $response->assertJsonPath('data.slug', $article->slug);
    }

    public function test_show_returns_404_when_article_not_found(): void
    {
        $response = $this->getJson('/api/galnet/news/nonexistent-article-slug');

        $response->assertNotFound();
    }

    public function test_show_strips_html_tags_except_allowed(): void
    {
        $article = GalnetNews::factory()->create([
            'content' => '<h1>Title</h1><p>Body text</p><br /><div>Section</div><script>alert("xss")</script>',
        ]);

        $response = $this->getJson("/api/galnet/news/{$article->slug}");

        $response->assertOk();
        $content = $response->json('data.content');
        $this->assertStringNotContainsString('<h1>', $content);
        $this->assertStringNotContainsString('<script>', $content);
        $this->assertStringContainsString('<p>', $content);
        $this->assertStringContainsString('<div>', $content);
    }

    public function test_show_formats_bold_text_in_content(): void
    {
        $article = GalnetNews::factory()->create([
            'content' => 'The leader said *important statement* about the conflict.',
        ]);

        $response = $this->getJson("/api/galnet/news/{$article->slug}");

        $response->assertOk();
        $content = $response->json('data.content');
        $this->assertStringContainsString('<strong>important statement</strong>', $content);
    }

    public function test_show_doubles_line_breaks_in_content(): void
    {
        $article = GalnetNews::factory()->create([
            'content' => 'First paragraph.<br />Second paragraph.',
        ]);

        $response = $this->getJson("/api/galnet/news/{$article->slug}");

        $response->assertOk();
        $content = $response->json('data.content');
        $this->assertStringContainsString('<br /><br />', $content);
    }

    public function test_destroy_deletes_article(): void
    {
        $article = GalnetNews::factory()->create();

        $response = $this->deleteJson("/api/galnet/news/{$article->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('galnet_news', ['id' => $article->id]);
    }

    public function test_destroy_returns_404_for_nonexistent_article(): void
    {
        $response = $this->deleteJson('/api/galnet/news/999999');

        $response->assertNotFound();
    }

    public function test_deleted_article_is_not_found_via_show(): void
    {
        $article = GalnetNews::factory()->create();
        $article->delete();

        $response = $this->getJson("/api/galnet/news/{$article->slug}");

        $response->assertNotFound();
    }
}
