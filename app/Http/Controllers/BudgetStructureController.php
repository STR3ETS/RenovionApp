<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\BudgetChapter;
use App\Models\BudgetParagraph;
use App\Support\BudgetChapterLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * De indeling van een begroting: hoofdstukken en paragrafen toevoegen, hernoemen
 * en verwijderen. Alleen zolang de begroting concept is.
 */
class BudgetStructureController extends Controller
{
    public function storeChapter(Request $request, Budget $budget): RedirectResponse
    {
        $this->assertDraft($budget);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:8'],
            'name' => ['nullable', 'string', 'max:160'],
        ]);

        $chapter = $budget->chapters()->create([
            'code' => $validated['code'],
            // Staat het nummer in de bibliotheek, dan pakken we die naam.
            'name' => $validated['name'] ?: (BudgetChapterLibrary::CHAPTERS[$validated['code']] ?? $validated['code']),
            'position' => (int) $budget->chapters()->max('position') + 1,
        ]);

        // Een hoofdstuk zonder paragraaf kan geen regels bevatten.
        $chapter->paragraphs()->create(['code' => '.01', 'name' => 'Algemeen', 'position' => 0]);

        return back()->with('success', "Hoofdstuk {$chapter->code} toegevoegd.");
    }

    public function updateChapter(Request $request, BudgetChapter $chapter): RedirectResponse
    {
        $this->assertDraft($chapter->budget);

        $chapter->update($request->validate([
            'code' => ['sometimes', 'string', 'max:8'],
            'name' => ['sometimes', 'string', 'max:160'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ]));

        return back()->with('success', 'Hoofdstuk bijgewerkt.');
    }

    public function destroyChapter(BudgetChapter $chapter): RedirectResponse
    {
        $this->assertDraft($chapter->budget);

        $code = $chapter->code;
        $chapter->delete();

        return back()->with('success', "Hoofdstuk {$code} verwijderd.");
    }

    public function storeParagraph(Request $request, BudgetChapter $chapter): RedirectResponse
    {
        $this->assertDraft($chapter->budget);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
        ]);

        $position = (int) $chapter->paragraphs()->max('position') + 1;

        $chapter->paragraphs()->create([
            'name' => $validated['name'],
            'code' => sprintf('.%02d', $position + 1),
            'position' => $position,
        ]);

        return back()->with('success', 'Paragraaf toegevoegd.');
    }

    public function updateParagraph(Request $request, BudgetParagraph $paragraph): RedirectResponse
    {
        $this->assertDraft($paragraph->chapter->budget);

        $paragraph->update($request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'code' => ['sometimes', 'string', 'max:8'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ]));

        return back()->with('success', 'Paragraaf bijgewerkt.');
    }

    public function destroyParagraph(BudgetParagraph $paragraph): RedirectResponse
    {
        $this->assertDraft($paragraph->chapter->budget);

        $paragraph->delete();

        return back()->with('success', 'Paragraaf verwijderd.');
    }

    private function assertDraft(Budget $budget): void
    {
        abort_if(! $budget->isDraft(), 422, 'Deze begroting is definitief. Heropen hem eerst.');
    }
}
