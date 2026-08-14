<?php

namespace Tests\Unit\NewsSources;

use App\Models\SourceSite;
use App\Services\NewsSources\ArticleContentExtractor;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ArticleContentExtractorTest extends TestCase
{
    public function test_it_preserves_a_complete_wordpress_body_instead_of_overwriting_it_with_page_chrome(): void
    {
        Http::fake([
            'forbes.test/nota' => Http::response($this->pageWithMobileSearchBeforeArticle()),
        ]);

        $fullBody = collect(range(1, 8))
            ->map(fn (int $number) => '<p>Párrafo completo '.$number.' con información relevante sobre política económica, cooperación internacional y el desarrollo responsable de herramientas de inteligencia artificial.</p>')
            ->implode('');

        $result = app(ArticleContentExtractor::class)->extract($this->sourceSite(), [
            'titulo' => 'Una nota completa',
            'url' => 'https://forbes.test/nota',
            'contenido' => strip_tags($fullBody),
            'contenido_html' => $fullBody,
        ]);

        $this->assertStringContainsString('Párrafo completo 8', $result['contenido_html']);
        $this->assertStringNotContainsString('Busqueda', $result['contenido_html']);
        $this->assertGreaterThan(900, mb_strlen($result['contenido']));
        Http::assertNothingSent();
    }

    public function test_it_selects_the_explicit_entry_content_and_ignores_unrelated_article_elements(): void
    {
        Http::fake(fn (Request $request) => str_contains($request->url(), 'forbes.test/nota')
            ? Http::response($this->pageWithMobileSearchBeforeArticle())
            : Http::response('', 404));

        $result = app(ArticleContentExtractor::class)->extract($this->sourceSite(), [
            'titulo' => 'Título inicial',
            'url' => 'https://forbes.test/nota',
            'contenido' => '',
            'contenido_html' => '',
        ]);

        $this->assertStringContainsString('Contenido principal uno', $result['contenido_html']);
        $this->assertStringContainsString('Contenido principal cuatro', $result['contenido_html']);
        $this->assertStringNotContainsString('Busqueda', $result['contenido_html']);
        $this->assertStringNotContainsString('Nota recomendada', $result['contenido_html']);
    }

    public function test_it_removes_promotional_paragraphs_from_a_complete_feed_body(): void
    {
        $body = <<<'HTML'
            <p>Primer párrafo de una noticia completa con suficientes detalles para explicar el acontecimiento y aportar contexto verificable a los lectores.</p>
            <p>Segundo párrafo de una noticia completa con información económica, antecedentes y declaraciones de las personas involucradas.</p>
            <p>Tercer párrafo de una noticia completa que desarrolla las consecuencias del acuerdo y los siguientes pasos anunciados oficialmente.</p>
            <p>Cuarto párrafo de una noticia completa con datos adicionales que permiten comprender la relevancia pública de la información publicada.</p>
            <p>Quinto párrafo de una noticia completa con el cierre editorial y el contexto necesario para utilizarla como fuente de generación.</p>
            <p>Te puede interesar: otra nota que no forma parte de este artículo.</p>
            <p>Este artículo fue publicado originalmente en Forbes.</p>
            HTML;

        $result = app(ArticleContentExtractor::class)->extract($this->sourceSite(), [
            'titulo' => 'Nota con promociones',
            'url' => 'https://forbes.test/nota',
            'contenido' => strip_tags($body),
            'contenido_html' => $body,
        ]);

        $this->assertStringContainsString('Quinto párrafo', $result['contenido_html']);
        $this->assertStringNotContainsString('Te puede interesar', $result['contenido_html']);
        $this->assertStringNotContainsString('publicado originalmente', $result['contenido_html']);
        Http::assertNothingSent();
    }

    public function test_it_extracts_a_blog_content_body_built_with_leaf_divs(): void
    {
        Http::fake([
            'jornada.test/quintana-roo/123/nota' => Http::response(<<<'HTML'
                <!doctype html>
                <html>
                    <head>
                        <meta property="og:title" content="Título limpio de la nota">
                        <meta property="og:url" content="https://jornada.test/quintanaroo/123/nota">
                        <script type="application/ld+json">
                            {"@type":"NewsArticle","datePublished":"14/08/2026"}
                        </script>
                    </head>
                    <body>
                        <div class="single-blog-content">
                            <div class="post-meta">
                                <p><strong>Reportera</strong></p>
                                <p>14/08/2026 | Quintana Roo</p>
                            </div>
                            <p>
                                <div>Primer bloque editorial con información verificable y suficiente sobre el acontecimiento ocurrido en Quintana Roo.</div>
                                <div><br></div>
                                <div>Segundo bloque editorial que aporta antecedentes, declaraciones y contexto relevante para comprender la noticia completa.</div>
                                <div><br></div>
                                <div>Tercer bloque editorial con las consecuencias anunciadas por las autoridades y los siguientes pasos de la investigación.</div>
                                <div><br></div>
                                <div>Cuarto bloque editorial que cierra la publicación con información adicional útil para las personas lectoras.</div>
                            </p>
                        </div>
                        <div class="single-blog-content">
                            <h4>Nota relacionada</h4>
                            <p>Este texto no pertenece al artículo principal.</p>
                        </div>
                    </body>
                </html>
                HTML),
        ]);

        $result = app(ArticleContentExtractor::class)->extract(new SourceSite([
            'name' => 'La Jornada Maya',
            'url' => 'https://jornada.test/quintana-roo',
            'type' => SourceSite::TYPE_HTML,
            'language' => 'es',
            'auth_method' => SourceSite::AUTH_NONE,
        ]), [
            'titulo' => 'Título desde el listado',
            'url' => 'https://jornada.test/quintana-roo/123/nota',
            'contenido' => '',
            'contenido_html' => '',
        ]);

        $this->assertSame('Título limpio de la nota', $result['titulo']);
        $this->assertSame('2026-08-14T00:00:00-06:00', $result['fecha']);
        $this->assertStringContainsString('Primer bloque editorial', $result['contenido_html']);
        $this->assertStringContainsString('Cuarto bloque editorial', $result['contenido_html']);
        $this->assertStringNotContainsString('Nota relacionada', $result['contenido_html']);
        $this->assertGreaterThan(400, mb_strlen($result['contenido']));
    }

    private function sourceSite(): SourceSite
    {
        return new SourceSite([
            'name' => 'Forbes',
            'url' => 'https://forbes.test',
            'type' => SourceSite::TYPE_WORDPRESS_REST,
            'language' => 'es',
            'auth_method' => SourceSite::AUTH_NONE,
        ]);
    }

    private function pageWithMobileSearchBeforeArticle(): string
    {
        return <<<'HTML'
            <!doctype html>
            <html>
                <head>
                    <meta property="og:title" content="Título correcto">
                    <meta property="og:image" content="https://forbes.test/imagen.jpg">
                </head>
                <body>
                    <article class="mobile_search__holder">
                        <h3 class="mobile_search__title">Busqueda</h3>
                        <div class="quick_links"><h4>Enlaces Rápidos</h4></div>
                    </article>
                    <main>
                        <article class="recommended-card">
                            <h2>Nota recomendada</h2>
                            <p>Este contenido pertenece a otra publicación.</p>
                        </article>
                        <div class="link-braces entry-content">
                            <p>Contenido principal uno con información suficiente para reconocer el cuerpo editorial de la noticia publicada.</p>
                            <p>Contenido principal dos que continúa el relato y agrega hechos importantes para comprender lo ocurrido.</p>
                            <p>Contenido principal tres con antecedentes, contexto y declaraciones relacionadas directamente con la noticia.</p>
                            <p>Contenido principal cuatro que cierra la información y conserva únicamente el texto relevante del artículo.</p>
                        </div>
                    </main>
                </body>
            </html>
            HTML;
    }
}
