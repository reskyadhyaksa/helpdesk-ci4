<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class FormatCommentTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        helper('format');
    }

    public function testEscapesHtmlBeforeFormatting(): void
    {
        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', format_comment('<script>alert(1)</script>'));
        $this->assertSame('<strong>&lt;b&gt;</strong>', format_comment('**<b>**'));
    }

    public function testBoldItalicAndInlineCode(): void
    {
        $this->assertSame('<strong>tebal</strong> dan <em>miring</em>', format_comment('**tebal** dan *miring*'));
        $this->assertSame('jalankan <code>php spark</code>', format_comment('jalankan `php spark`'));
    }

    public function testMarkersInsideCodeAreLeftAlone(): void
    {
        $this->assertSame('<code>**a** *b*</code>', format_comment('`**a** *b*`'));
    }

    public function testBulletAndNumberedLists(): void
    {
        $this->assertSame('<ul><li>satu</li><li>dua</li></ul>', format_comment("- satu\n- dua"));
        $this->assertSame('<ol><li>a</li><li>b</li></ol>', format_comment("1. a\n2. b"));
    }

    public function testTextAroundListsKeepsLineBreaks(): void
    {
        $this->assertSame("Halo\nIni daftar:<ul><li>satu</li></ul>Terima kasih", format_comment("Halo\nIni daftar:\n- satu\nTerima kasih"));
    }

    public function testKnownMentionsAreWrappedAndUnknownNamesAreNot(): void
    {
        $html = format_comment('Mohon cek @Siti Rahma dan @Budi, bukan @Orang atau email a@siti.id', ['Siti', 'Siti Rahma', 'Budi']);

        $this->assertSame(
            'Mohon cek <span class="mention">@Siti Rahma</span> dan <span class="mention">@Budi</span>, bukan @Orang atau email a@siti.id',
            $html,
        );
    }

    public function testMentionNamesAreEscapedBeforeMatching(): void
    {
        $this->assertSame('<span class="mention">@O&#039;Neil</span> ok', format_comment("@O'Neil ok", ["O'Neil"]));
    }

    public function testUnbalancedMarkersStayAsText(): void
    {
        $this->assertSame('2 * 3 = 6 dan **belum ditutup', format_comment('2 * 3 = 6 dan **belum ditutup'));
    }
}
