@extends('layouts.manager')

@section('title', 'Compare Schedules')

@section('content')
    <div class="mb-8">
        <h1 class="text-display">Compare Candidates</h1>
        <p class="text-body text-gray-600 mt-2">GA generated {{ count($candidates) }} candidates &bull; RF evaluated
            profitability</p>
    </div>

    <div class="card p-6 mb-6">
        <div class="text-caption text-gray-600 mb-2">SCHEDULE INFO</div>
        <div class="flex gap-6 text-body">
            <div><span class="text-gray-500">Period:</span> <span
                    class="font-mono font-semibold">{{ $poolInfo['start_date'] }}</span> <span
                    class="text-gray-400">({{ $poolInfo['days'] }} days)</span></div>
            <div><span class="text-gray-500">Pool:</span> <span
                    class="font-mono font-semibold">{{ $poolInfo['employee_count'] }}</span> <span
                    class="text-gray-400">employees</span></div>
        </div>
    </div>

    <div class="card p-6 mb-6">
        <h2 class="text-title mb-4">Candidates Comparison</h2>
        <div class="overflow-x-auto">
            <table class="table-minimal w-full">
                <thead>
                    <tr>
                        <th>CANDIDATE</th>
                        <th>GA FITNESS</th>
                        <th>RF SCORE</th>
                        <th>FINAL SCORE</th>
                        <th>TOTAL SALARY</th>
                        <th>ACTIVE EMPS</th>
                        <th>ASSIGNMENTS</th>
                        <th>VIOLATIONS</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        /*
                         * Kandidat datang dari Python sudah dalam urutan C1, C2, C3, C4, C5
                         * (diversity selection — bukan sorted by score).
                         * Label BEST dan LEAST RECOMMENDED ditentukan di sini berdasarkan
                         * final_score: BEST = tertinggi, LEAST = terendah.
                         * Baris tabel TIDAK diurutkan ulang — C1 tetap baris 1, dst.
                         */
                        $sorted = collect($candidates)->sortByDesc(fn($c) => $c['final_score'] ?? 0);
                        $bestCandidateId  = $sorted->first()['candidate_id'] ?? null;
                        $leastCandidateId = $sorted->last()['candidate_id'] ?? null;
                        // Jika hanya 1 kandidat, tidak ada "least"
                        if (count($candidates) <= 1) $leastCandidateId = null;
                    @endphp

                    @foreach($candidates as $candidate)
                        @php
                            $isBest    = $candidate['candidate_id'] === $bestCandidateId;
                            $isLeast   = $candidate['candidate_id'] === $leastCandidateId;
                            $finalScore  = $candidate['final_score'] ?? 0;
                            $rfScore     = $candidate['rf_profit_score'] ?? 0;
                            $totalSalary = $candidate['summary']['total_salary'] ?? 0;
                        @endphp
                        <tr class="{{ $isBest ? 'bg-green-50' : ($isLeast ? 'bg-red-50' : '') }}">
                            <td>
                                <div class="flex items-center gap-2">
                                    <span class="badge badge-secondary font-mono">{{ $candidate['candidate_id'] }}</span>
                                    @if($isBest)
                                        <span class="badge badge-success text-xs">BEST</span>
                                    @elseif($isLeast)
                                        <span class="badge badge-danger text-xs">LEAST RECOMMENDED</span>
                                    @endif
                                </div>
                            </td>
                            <td class="font-mono font-semibold">
                                {{ number_format($candidate['summary']['ga_fitness'], 2, '.', ',') }}
                            </td>
                            <td
                                class="font-mono font-semibold {{ $rfScore >= 70 ? 'text-green-600' : ($rfScore >= 40 ? 'text-yellow-600' : 'text-orange-500') }}">
                                {{ number_format($rfScore, 2, '.', ',') }}
                            </td>
                            <td class="font-mono font-semibold {{ $isBest ? 'text-green-700' : 'text-gray-700' }}">
                                {{ number_format($finalScore, 2, '.', ',') }}
                            </td>
                            {{--
                            Total Salary dalam USD (sesuai satuan data CSV salary).
                            Dihitung oleh salary_calculator.py berdasarkan assignment aktual:
                            - shift malam × 1.20
                            - sertifikasi × 1.15
                            - malam→pagi + 10% bonus
                            Dibagi 1000 untuk tampilkan dalam ribuan USD (K).
                            --}}
                            <td class="font-mono">
                                ${{ number_format($totalSalary / 1000, 1) }}K
                            </td>
                            <td class="font-mono">{{ $candidate['summary']['active_employees'] }}</td>
                            <td class="font-mono">{{ $candidate['summary']['total_assignments'] }}</td>
                            <td>
                                <span
                                    class="badge badge-{{ $candidate['summary']['hard_violation_count'] > 0 ? 'danger' : 'success' }}">
                                    H:{{ $candidate['summary']['hard_violation_count'] }}
                                </span>
                                <span class="badge badge-warning">S:{{ $candidate['summary']['soft_violation_count'] }}</span>
                            </td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('manager.schedules.candidate.show', ['schedule' => $schedule, 'candidateCode' => $candidate['candidate_id']]) }}"
                                        class="btn btn-secondary btn-sm">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        <span>VIEW</span>
                                    </a>
                                    <form method="POST" action="{{ route('manager.schedules.publish', $schedule) }}" class="inline"
                                        onsubmit="return confirm('Publish this schedule?')">
                                        @csrf
                                        <input type="hidden" name="candidate_id" value="{{ $candidate['candidate_id'] }}">
                                        <button type="submit" class="btn btn-success btn-sm">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span>PUBLISH</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Deskripsi BEST dan cara pembacaan tabel --}}
    <div class="card p-6">
        <h3 class="text-headline mb-4">How to Read This Table</h3>
        <div class="space-y-3 text-caption text-gray-600">

            <div class="flex gap-2">
                <span class="badge badge-success text-xs shrink-0 mt-0.5">BEST</span>
                <div>
                    <strong>Best Candidate</strong> — kandidat dengan
                    <span class="font-semibold text-gray-800">Final Score tertinggi</span>,
                    gabungan GA Fitness (kualitas operasional) dan RF Profit Score (kualitas finansial):
                    <div class="mt-1 font-mono text-xs bg-gray-100 rounded px-2 py-1 inline-block">
                        Final Score = (GA Fitness norm &times; 50%) + (RF Profit Score &times; 50%)
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        Label ini bisa jatuh di baris mana pun (C1–C5) — urutan baris tidak menentukan kualitas.
                    </p>
                </div>
            </div>

            <div class="flex gap-2">
                <span class="badge badge-danger text-xs shrink-0 mt-0.5">LEAST RECOMMENDED</span>
                <div>
                    <strong>Least Recommended</strong> — kandidat dengan
                    <span class="font-semibold text-gray-800">Final Score terendah</span> di antara semua kandidat.
                    Bukan berarti jadwal ini buruk secara absolut — hanya yang paling rendah
                    relatif dalam batch ini. Jika semua kandidat berkualitas baik (H:0, RF score tinggi),
                    label ini hanya menandai yang sedikit lebih rendah dari yang lain.
                    Hindari mempublish jadwal ini jika ada alternatif yang lebih baik.
                </div>
            </div>

            <div><strong>GA Fitness:</strong> Skor constraint satisfaction dari Genetic Algorithm — lebih tinggi berarti
                lebih sedikit pelanggaran jadwal (hard &amp; soft constraint)</div>

            <div><strong>RF Score (0–100):</strong> Prediksi profitabilitas operasional absolut dari Random Forest,
                berdasarkan 12 fitur jadwal: coverage rate, dept tier weight, certified ratio, senior ratio,
                night ratio, malam→pagi ratio, cost ratio, cluster balance, hard violation count,
                soft violation ratio, dayoff violation ratio, dan avg job level.
                Nilai ini <strong>tidak relatif antar kandidat</strong> — dua jadwal yang sama-sama baik
                bisa mendapat skor yang sama. Panduan: ≥70 = sangat baik, 40–70 = baik, &lt;40 = perlu perhatian.
            </div>

            <div><strong>Total Salary:</strong> Estimasi total biaya gaji jadwal ini dalam USD, dihitung per assignment
                aktual oleh salary_calculator dengan multiplier:
                shift malam ×1.2, sertifikasi ×1.15, bonus malam→pagi +10%.
            </div>

            <div class="pt-1 border-t border-gray-200">
                <strong>Rekomendasi:</strong>
                Pilih kandidat <span class="text-green-600 font-semibold">BEST</span> (Final Score tertinggi)
                dengan <span class="text-red-600 font-semibold">H:0</span> (tidak ada hard violation).
                Jika BEST punya H &gt; 0, pertimbangkan kandidat lain dengan H:0 meskipun Final Score-nya lebih rendah.
            </div>
        </div>
    </div>

    <div class="mt-6 flex justify-end">
        <a href="{{ route('manager.schedules.create') }}" class="btn btn-secondary">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            <span>BACK TO POOL</span>
        </a>
    </div>
@endsection