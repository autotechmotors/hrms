<?php

namespace App\Services\Operations;

use App\Models\SalarySheet;
use App\Models\SalarySheetLine;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalarySheetExporter
{
    public function excel(SalarySheet $sheet): StreamedResponse
    {
        $sheet->loadMissing(['lines.employee.department', 'lines.employee.designation', 'company', 'department']);
        $book = new Spreadsheet;
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $summary = $book->getActiveSheet();
        $summary->setTitle('Summary');
        $this->summary($summary, $sheet);

        foreach ($sheet->lines as $line) {
            $tab = $book->createSheet();
            $tab->setTitle($this->tabTitle($line));
            $this->person($tab, $sheet, $line);
        }

        $book->setActiveSheetIndex(0);
        $name = $this->filename($sheet, 'xlsx');

        return response()->streamDownload(function () use ($book) {
            $writer = new Xlsx($book);
            $writer->save('php://output');
        }, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function pdf(SalarySheet $sheet): StreamedResponse|Response
    {
        $sheet->loadMissing(['lines.employee.department', 'lines.employee.designation', 'company', 'department']);
        $html = view('operations.salary-sheets.print', ['sheet' => $sheet])->render();
        $name = $this->filename($sheet, 'pdf');

        if (! class_exists(Dompdf::class)) {
            return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        $dompdf = new Dompdf(['isRemoteEnabled' => false]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A3', 'landscape');
        $dompdf->render();

        return response()->streamDownload(function () use ($dompdf) {
            echo $dompdf->output();
        }, $name, ['Content-Type' => 'application/pdf']);
    }

    public function filename(SalarySheet $sheet, string $ext): string
    {
        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', $sheet->title) ?: 'salary';

        return $slug.'-'.$sheet->starts_on->format('Ymd').'-'.$sheet->ends_on->format('Ymd').'.'.$ext;
    }

    private function summary(Worksheet $tab, SalarySheet $sheet): void
    {
        $tab->setCellValue('A1', $sheet->company?->name ?: 'Salary sheet');
        $tab->setCellValue('A2', $sheet->title.' · '.$sheet->starts_on->format('d M Y').' to '.$sheet->ends_on->format('d M Y'));
        $headers = ['Code', 'Name', 'Department', 'Job title', 'Monthly', 'Hours / day', 'Day pay', 'Attended hours', 'OT hours', 'Paid leave', 'Unpaid leave', 'Absence', 'Earned', 'Advance', 'Other deductions', 'Net'];
        foreach ($headers as $index => $header) {
            $tab->setCellValue([$index + 1, 4], $header);
        }
        $tab->getStyle('A4:P4')->applyFromArray($this->headStyle());

        $row = 5;
        foreach ($sheet->lines as $line) {
            $person = $line->employee;
            $tab->fromArray([
                $person?->employee_code,
                $person?->full_name,
                $person?->department?->name,
                $person?->designation?->name,
                (float) $line->monthly_salary,
                (float) $line->hours_per_day,
                (float) $line->hourly_rate,
                round($line->attended_minutes / 60, 2),
                round($line->ot_minutes / 60, 2),
                (float) $line->paid_leave_days,
                (float) $line->unpaid_leave_days,
                (float) $line->absence_days,
                (float) $line->earned,
                (float) $line->advance,
                (float) $line->deductions,
                (float) $line->net,
            ], null, 'A'.$row);
            $row++;
        }

        if ($sheet->lines->isNotEmpty()) {
            $tab->setCellValue('L'.$row, 'Total');
            foreach (['M', 'N', 'O', 'P'] as $column) {
                $tab->setCellValue($column.$row, '=SUM('.$column.'5:'.$column.($row - 1).')');
            }
            $tab->getStyle('A'.$row.':P'.$row)->getFont()->setBold(true);
        }

        foreach (range('A', 'P') as $column) {
            $tab->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function person(Worksheet $tab, SalarySheet $sheet, SalarySheetLine $line): void
    {
        $person = $line->employee;
        $dates = $this->dates($sheet);
        $tab->setCellValue('A1', 'Person ID');
        $tab->setCellValue('B1', $person?->employee_code);
        $tab->setCellValue('C1', 'Employee Name');
        $tab->setCellValue('D1', $person?->full_name);
        $tab->setCellValue('E1', 'Department');
        $tab->setCellValue('F1', $person?->department?->name);
        $tab->setCellValue('G1', 'Joining Date');
        $tab->setCellValue('H1', $person?->joining_date?->format('d M Y'));
        $tab->setCellValue('I1', 'Job Title');
        $tab->setCellValue('J1', $person?->designation?->name);
        $tab->getStyle('A1:J1')->getFont()->setBold(true);

        $labels = [
            3 => 'Date',
            4 => 'Check-In',
            5 => 'Check-Out',
            6 => 'Late',
            7 => 'OT',
            8 => 'Early Leave',
            9 => 'Break',
            10 => 'Attended',
            11 => 'Status',
            12 => 'Company holiday',
            13 => 'Leave',
            14 => 'Gate pass',
        ];
        foreach ($labels as $row => $label) {
            $tab->setCellValue('A'.$row, $label);
        }

        foreach ($dates as $index => $date) {
            $column = $index + 2;
            $cell = $line->days[$date] ?? [];
            $tab->setCellValue([$column, 3], Carbon::parse($date)->format('d'));
            $tab->setCellValue([$column, 4], $cell['in'] ?? '—');
            $tab->setCellValue([$column, 5], $cell['out'] ?? '—');
            $tab->setCellValue([$column, 6], (int) ($cell['late'] ?? 0));
            $tab->setCellValue([$column, 7], (int) ($cell['ot'] ?? 0));
            $tab->setCellValue([$column, 8], (int) ($cell['early'] ?? 0));
            $tab->setCellValue([$column, 9], (int) ($cell['break'] ?? 0));
            $tab->setCellValue([$column, 10], (int) ($cell['attended'] ?? 0));
            $tab->setCellValue([$column, 11], $cell['status'] ?? '—');
            $tab->setCellValue([$column, 12], ($cell['holiday'] ?? '') !== '' ? $cell['holiday'] : '—');
            $tab->setCellValue([$column, 13], ($cell['leave'] ?? '') !== '' ? $cell['leave'] : '—');
            $tab->setCellValue([$column, 14], ($cell['gate'] ?? '') !== '' ? $cell['gate'] : '—');
        }

        $last = $this->column(count($dates) + 1);
        $tab->getStyle('A3:'.$last.'3')->applyFromArray($this->headStyle());
        $tab->getStyle('A11:'.$last.'14')->getFont()->setBold(true);
        $tab->getStyle('A3:'.$last.'14')->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E4E4EE']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $tab->getStyle('A3:A14')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $tab->setCellValue('A16', 'Summary');
        $tab->getStyle('A16')->getFont()->setBold(true);
        $pairs = [
            ['Attended', $this->hours($line->attended_minutes)],
            ['Paid hours', $this->hours($line->paid_minutes)],
            ['Overtime', $this->hours($line->ot_minutes)],
            ['Late', $this->hours($line->late_minutes)],
            ['Early leave', $this->hours($line->early_minutes)],
            ['Break', $this->hours($line->break_minutes)],
            ['Paid leave days', (float) $line->paid_leave_days],
            ['Unpaid leave days', (float) $line->unpaid_leave_days],
            ['Absence days', (float) $line->absence_days],
            ['Monthly salary', (float) $line->monthly_salary],
            ['Hours per day', (float) $line->hours_per_day],
            ['Day pay', (float) $line->hourly_rate],
            ['Salary cutoff', (float) ($line->breakdown['cutoff'] ?? 0)],
            ['Earned', (float) $line->earned],
            ['Advance', (float) $line->advance],
            ['Other deductions', (float) $line->deductions],
            ['Net pay', (float) $line->net],
        ];
        foreach ($pairs as $index => $pair) {
            $row = 17 + $index;
            $tab->setCellValue('A'.$row, $pair[0]);
            $tab->setCellValue('B'.$row, $pair[1]);
        }
        $tab->getStyle('A'.(16 + count($pairs)))->getFont()->setBold(true);
        $tab->getStyle('B'.(16 + count($pairs)))->getFont()->setBold(true);

        $row = 17 + count($pairs) + 1;
        foreach ($line->breakdown['deductions'] ?? [] as $item) {
            $tab->setCellValue('A'.$row, $item['name']);
            $tab->setCellValue('B'.$row, (float) $item['amount']);
            $row++;
        }
        foreach ($line->breakdown['advances'] ?? [] as $item) {
            $tab->setCellValue('A'.$row, $item['name']);
            $tab->setCellValue('B'.$row, (float) $item['amount']);
            $row++;
        }
        if (($line->breakdown['cuts'] ?? []) !== []) {
            $row++;
            $tab->setCellValue('A'.$row, 'Date');
            $tab->setCellValue('B'.$row, 'Window');
            $tab->setCellValue('C'.$row, 'In');
            $tab->setCellValue('D'.$row, 'Out');
            $tab->setCellValue('E'.$row, 'Minutes');
            $tab->setCellValue('F'.$row, 'Rupees');
            $tab->setCellValue('G'.$row, 'Reason');
            $tab->getStyle('A'.$row.':G'.$row)->getFont()->setBold(true);
            foreach ($line->breakdown['cuts'] as $cut) {
                $row++;
                $tab->setCellValue('A'.$row, $cut['date']);
                $tab->setCellValue('B'.$row, $cut['window']);
                $tab->setCellValue('C'.$row, $cut['in'] ?: '—');
                $tab->setCellValue('D'.$row, $cut['out'] ?: '—');
                $tab->setCellValue('E'.$row, (int) $cut['minutes']);
                $tab->setCellValue('F'.$row, (float) $cut['rupees']);
                $tab->setCellValue('G'.$row, trim(($cut['reason'] ?? '').(! empty($cut['detail']) ? ' · '.$cut['detail'] : '')));
            }
        }

        $tab->getColumnDimension('A')->setWidth(22);
        for ($i = 2; $i <= count($dates) + 1; $i++) {
            $tab->getColumnDimension($this->column($i))->setWidth(8);
        }
    }

    /**
     * @return list<string>
     */
    private function dates(SalarySheet $sheet): array
    {
        $dates = [];
        $cursor = $sheet->starts_on->copy();
        while ($cursor->lte($sheet->ends_on)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
    }

    private function hours(int $minutes): float
    {
        return round($minutes / 60, 2);
    }

    private function column(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    private function tabTitle(SalarySheetLine $line): string
    {
        $code = preg_replace('/[^\w\- ]+/', '', (string) $line->employee?->employee_code) ?: 'person';

        return substr($code, 0, 31);
    }

    /**
     * @return array<string, mixed>
     */
    private function headStyle(): array
    {
        return [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E10613']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
    }
}
