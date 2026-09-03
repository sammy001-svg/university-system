<?php
declare(strict_types=1);

namespace App\Controllers\Exams;

use App\Core\ResourceController;
use App\Models\GradeScale;

final class GradeScaleController extends ResourceController
{
    protected string $title = 'Grading Scale';
    protected string $entity = 'Grade Band';
    protected string $routeBase = '/exams/grade-scale';
    protected string $permission = 'results';
    protected string $icon = 'chart';
    protected string $lede = 'Score bands, letter grades and grade points used to compute GPA.';
    protected string $defaultSort = 'min_score';
    protected string $defaultDir = 'desc';

    public function __construct()
    {
        $this->model = new GradeScale();
    }

    protected function columns(): array
    {
        return [
            ['key' => 'grade', 'label' => 'Grade', 'type' => 'strong'],
            ['key' => 'min_score', 'label' => 'From', 'type' => 'number', 'align' => 'center'],
            ['key' => 'max_score', 'label' => 'To', 'type' => 'number', 'align' => 'center'],
            ['key' => 'grade_point', 'label' => 'Grade point', 'type' => 'number', 'align' => 'center'],
            ['key' => 'remarks', 'label' => 'Remarks'],
            ['key' => 'is_pass', 'label' => 'Pass', 'type' => 'bool', 'align' => 'center'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'grade', 'label' => 'Grade letter', 'width' => 3, 'required' => true],
            ['name' => 'grade_point', 'label' => 'Grade point', 'type' => 'number', 'width' => 3, 'required' => true],
            ['name' => 'min_score', 'label' => 'Minimum score', 'type' => 'number', 'width' => 3, 'required' => true],
            ['name' => 'max_score', 'label' => 'Maximum score', 'type' => 'number', 'width' => 3, 'required' => true],
            ['name' => 'remarks', 'label' => 'Remarks', 'width' => 6],
            ['name' => 'is_pass', 'label' => 'Counts as a pass', 'type' => 'checkbox', 'width' => 6],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'grade' => 'required|max:5|unique:grade_scales,grade,' . ($id ?? ''),
            'grade_point' => 'required|numeric|between:0,5',
            'min_score' => 'required|numeric|between:0,100',
            'max_score' => 'required|numeric|between:0,100',
            'remarks' => 'nullable|max:60',
            'is_pass' => 'nullable|integer',
        ];
    }
}
