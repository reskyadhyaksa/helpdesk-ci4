<?php

use App\Services\CommentSanitizer;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class CommentSanitizerTest extends CIUnitTestCase
{
    private function clean(string $html): string
    {
        return (new CommentSanitizer())->clean($html);
    }

    public function testKeepsSimpleFormatting(): void
    {
        $this->assertSame('<p>Halo <strong>tebal</strong> <em>miring</em> <code>kode</code></p>', $this->clean('<p>Halo <strong>tebal</strong> <em>miring</em> <code>kode</code></p>'));
        $this->assertSame('<ul><li>satu</li><li>dua</li></ul>', $this->clean('<ul><li>satu</li><li>dua</li></ul>'));
        $this->assertSame('<ol><li>a</li></ol>', $this->clean('<ol><li>a</li></ol>'));
    }

    public function testRenamesBoldItalicAndDivFromTheBrowser(): void
    {
        $this->assertSame('<p><strong>a</strong> <em>b</em></p><p>baris dua</p>', $this->clean('<div><b>a</b> <i>b</i></div><div>baris dua</div>'));
    }

    public function testDropsScriptsAndTheirContent(): void
    {
        $this->assertSame('<p>aman</p>', $this->clean('<p>aman</p><script>alert(1)</script><style>p{}</style><iframe src="x"></iframe>'));
    }

    public function testStripsAllAttributesAndUnknownTags(): void
    {
        $this->assertSame('<p>klik <strong>di sini</strong></p>', $this->clean('<p onclick="x()" style="color:red">klik <a href="javascript:alert(1)"><strong class="x">di sini</strong></a></p>'));
        $this->assertSame('gambar', $this->clean('<img src=x onerror=alert(1)>gambar'));
    }

    public function testKeepsOnlyWellFormedMentionSpans(): void
    {
        $this->assertSame(
            '<p><span class="mention" data-user-id="abc-123">@Siti</span> cek</p>',
            $this->clean('<p><span class="mention" data-user-id="abc-123" contenteditable="false" onclick="x">@Siti</span> cek</p>'),
        );
        $this->assertSame('<p>@X cek</p>', $this->clean('<p><span class="mention" data-user-id="&quot;&gt;&lt;script&gt;">@X</span> cek</p>'));
        $this->assertSame('<p>teks</p>', $this->clean('<p><span style="color:red">teks</span></p>'));
    }

    public function testTextIsEscaped(): void
    {
        $this->assertSame('<p>1 &lt; 2 &amp; 3</p>', $this->clean('<p>1 &lt; 2 &amp; 3</p>'));
    }

    public function testTrailingEmptyParagraphsAreRemoved(): void
    {
        $this->assertSame('<p>isi</p>', $this->clean('<p>isi</p><p><br></p><p></p>'));
        $this->assertSame('', $this->clean('<p><br></p>'));
    }

    public function testMentionIdsAndPlainText(): void
    {
        $sanitizer = new CommentSanitizer();
        $html      = $sanitizer->clean('<p>Tolong <span class="mention" data-user-id="u2">@Siti</span> &amp; <span class="mention" data-user-id="u2">@Siti</span></p><ul><li>satu</li></ul>');

        $this->assertSame(['u2'], $sanitizer->mentionIds($html));
        $this->assertSame('Tolong @Siti & @Siti satu', $sanitizer->plainText($html));
    }
}
