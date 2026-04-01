<?php
namespace WHAM_Reports;

defined( 'ABSPATH' ) || exit;

/**
 * Canonical month-based report period helper.
 */
final class Report_Period {

    private string $period;
    private \DateTimeZone $timezone;
    private \DateTimeImmutable $start;
    private \DateTimeImmutable $end;
    private \DateTimeImmutable $comparison_start;
    private \DateTimeImmutable $comparison_end;

    private function __construct( string $period, \DateTimeZone $timezone ) {
        if ( ! preg_match( '/^\d{4}-\d{2}$/', $period ) ) {
            throw new \InvalidArgumentException( 'Invalid report period.' );
        }

        $this->period   = $period;
        $this->timezone = $timezone;

        $start = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $period . '-01 00:00:00', $timezone );
        if ( ! $start ) {
            throw new \InvalidArgumentException( 'Invalid report period.' );
        }

        $this->start            = $start;
        $this->end              = $start->modify( 'last day of this month' )->setTime( 23, 59, 59 );
        $this->comparison_start = $start->modify( 'first day of previous month' )->setTime( 0, 0, 0 );
        $this->comparison_end   = $start->modify( 'last day of previous month' )->setTime( 23, 59, 59 );
    }

    public static function from_string( string $period ): self {
        return new self( $period, wp_timezone() );
    }

    public static function previous_completed_month( ?\DateTimeImmutable $reference = null ): self {
        $timezone  = wp_timezone();
        $reference = $reference ?: new \DateTimeImmutable( 'now', $timezone );
        $period    = $reference->modify( 'first day of previous month' )->format( 'Y-m' );

        return new self( $period, $timezone );
    }

    public static function sanitize( string $period ): string {
        $period = trim( $period );

        try {
            return self::from_string( $period )->period();
        } catch ( \InvalidArgumentException $e ) {
            return self::previous_completed_month()->period();
        }
    }

    public function period(): string {
        return $this->period;
    }

    public function label(): string {
        return $this->start->format( 'F Y' );
    }

    public function year(): string {
        return $this->start->format( 'Y' );
    }

    public function start_date(): string {
        return $this->start->format( 'Y-m-d' );
    }

    public function end_date(): string {
        return $this->end->format( 'Y-m-d' );
    }

    public function comparison_start_date(): string {
        return $this->comparison_start->format( 'Y-m-d' );
    }

    public function comparison_end_date(): string {
        return $this->comparison_end->format( 'Y-m-d' );
    }

    public function to_array(): array {
        return [
            'period'                => $this->period(),
            'period_label'          => $this->label(),
            'year'                  => $this->year(),
            'start_date'            => $this->start_date(),
            'end_date'              => $this->end_date(),
            'comparison_start_date' => $this->comparison_start_date(),
            'comparison_end_date'   => $this->comparison_end_date(),
            'timezone'              => $this->timezone->getName(),
        ];
    }
}
