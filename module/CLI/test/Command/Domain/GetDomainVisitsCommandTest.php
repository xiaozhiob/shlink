<?php

declare(strict_types=1);

namespace ShlinkioTest\Shlink\CLI\Command\Domain;

use Pagerfanta\Adapter\ArrayAdapter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shlinkio\Shlink\CLI\Command\Domain\GetDomainVisitsCommand;
use Shlinkio\Shlink\Common\Paginator\Paginator;
use Shlinkio\Shlink\Core\ShortUrl\Entity\ShortUrl;
use Shlinkio\Shlink\Core\ShortUrl\Helper\ShortUrlStringifierInterface;
use Shlinkio\Shlink\Core\Visit\Entity\Visit;
use Shlinkio\Shlink\Core\Visit\Entity\VisitLocation;
use Shlinkio\Shlink\Core\Visit\Model\Visitor;
use Shlinkio\Shlink\Core\Visit\VisitsStatsHelperInterface;
use Shlinkio\Shlink\IpGeolocation\Model\Location;
use ShlinkioTest\Shlink\CLI\CliTestUtilsTrait;
use Symfony\Component\Console\Tester\CommandTester;

class GetDomainVisitsCommandTest extends TestCase
{
    use CliTestUtilsTrait;

    private CommandTester $commandTester;
    private MockObject & VisitsStatsHelperInterface $visitsHelper;
    private MockObject & ShortUrlStringifierInterface $stringifier;

    protected function setUp(): void
    {
        $this->visitsHelper = $this->createMock(VisitsStatsHelperInterface::class);
        $this->stringifier = $this->createMock(ShortUrlStringifierInterface::class);

        $this->commandTester = $this->testerForCommand(
            new GetDomainVisitsCommand($this->visitsHelper, $this->stringifier),
        );
    }

    /** @test */
    public function outputIsProperlyGenerated(): void
    {
        $shortUrl = ShortUrl::createEmpty();
        $visit = Visit::forValidShortUrl($shortUrl, new Visitor('bar', 'foo', '', ''))->locate(
            VisitLocation::fromGeolocation(new Location('', 'Spain', '', 'Madrid', 0, 0, '')),
        );
        $domain = 'doma.in';
        $this->visitsHelper->expects($this->once())->method('visitsForDomain')->with(
            $domain,
            $this->anything(),
        )->willReturn(new Paginator(new ArrayAdapter([$visit])));
        $this->stringifier->expects($this->once())->method('stringify')->with($shortUrl)->willReturn(
            'the_short_url',
        );

        $this->commandTester->execute(['domain' => $domain]);
        $output = $this->commandTester->getDisplay();

        self::assertEquals(
            <<<OUTPUT
            +---------+---------------------------+------------+---------+--------+---------------+
            | Referer | Date                      | User agent | Country | City   | Short Url     |
            +---------+---------------------------+------------+---------+--------+---------------+
            | foo     | {$visit->getDate()->toAtomString()} | bar        | Spain   | Madrid | the_short_url |
            +---------+---------------------------+------------+---------+--------+---------------+

            OUTPUT,
            $output,
        );
    }
}
