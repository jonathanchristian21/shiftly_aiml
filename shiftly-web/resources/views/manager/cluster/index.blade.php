@extends('layouts.app')

@section('title', 'Employee Clustering')

@section('content')
<div class="mb-8">
    <h1 class="text-display">Employee Clustering</h1>
    <p class="text-caption mt-2">K-Means clustering for employee segmentation before GA optimization</p>
</div>

<div class="card p-6 mb-6" style="background: linear-gradient(135deg, #EFF5FF 0%, #DBEAFE 100%); border-color: #BFDBFE;">
    <div class="flex items-start gap-4">
        <div class="w-12 h-12 bg-sky rounded-lg flex items-center justify-center shrink-0">
            <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <div class="flex-1">
            <h3 class="text-headline mb-3">K-Means Role in the System</h3>
            <p class="text-body mb-4">Clustering is not just grouping employees. <strong>This is a pre-optimization phase</strong> that maps data into 4 functional profiles to make GA's population initialization smarter.</p>
            
            <div class="bg-white rounded-lg p-4 mb-4">
                <div class="text-tiny mb-3">7 INPUT FEATURES (NORMALIZED 0-1):</div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-gray-50 rounded flex items-center justify-center">
                            <svg class="w-4 h-4 text-ink-mute" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <span class="text-caption mono"><strong>age</strong></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-gray-50 rounded flex items-center justify-center">
                            <svg class="w-4 h-4 text-ink-mute" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                        <span class="text-caption mono"><strong>job_level</strong></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-gray-50 rounded flex items-center justify-center">
                            <svg class="w-4 h-4 text-ink-mute" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <span class="text-caption mono"><strong>salary</strong></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-gray-50 rounded flex items-center justify-center">
                            <svg class="w-4 h-4 text-ink-mute" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                            </svg>
                        </div>
                        <span class="text-caption mono"><strong>rating</strong></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-gray-50 rounded flex items-center justify-center">
                            <svg class="w-4 h-4 text-ink-mute" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <span class="text-caption mono"><strong>satisfied</strong></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-gray-50 rounded flex items-center justify-center">
                            <svg class="w-4 h-4 text-ink-mute" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                            </svg>
                        </div>
                        <span class="text-caption mono"><strong>certifications</strong></span>
                    </div>
                    <div class="flex items-center gap-2 col-span-2">
                        <div class="w-8 h-8 bg-gray-50 rounded flex items-center justify-center">
                            <svg class="w-4 h-4 text-ink-mute" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
                            </svg>
                        </div>
                        <span class="text-caption mono"><strong>education</strong> (PG=1, UG=0)</span>
                    </div>
                </div>
            </div>
            
            <div class="border-l-4 border-sky pl-4">
                <div class="text-body font-semibold mb-2">🔗 Connection to Genetic Algorithm</div>
                <p class="text-caption leading-relaxed">
                    When GA creates <strong>initial population</strong>, each shift per department is guaranteed to be filled with <strong>at least 1 employee from Senior Cluster (Profile A)</strong>. 
                    This makes the initial population already meet the hard constraint "minimum 1 PG per shift" from the first iteration, 
                    so <strong>GA converges faster</strong> than random initialization.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="stat-card">
        <div class="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center mb-3">
            <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
        </div>
        <div class="stat-label">Total Employees</div>
        <div class="stat-value text-blue-600">{{ $stats['total_employees'] }}</div>
    </div>
    <div class="stat-card">
        <div class="w-12 h-12 bg-emerald-50 rounded-lg flex items-center justify-center mb-3">
            <svg class="w-6 h-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <div class="stat-label">Clustered</div>
        <div class="stat-value text-emerald-600">{{ $stats['clustered'] }}</div>
    </div>
    <div class="stat-card">
        <div class="w-12 h-12 bg-amber-50 rounded-lg flex items-center justify-center mb-3">
            <svg class="w-6 h-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        </div>
        <div class="stat-label">Not Clustered</div>
        <div class="stat-value text-amber-600">{{ $stats['not_clustered'] }}</div>
    </div>
</div>

<div class="card p-6 mb-8 border-l-4 border-sky">
    <h2 class="text-title mb-4">Start Clustering Process</h2>
    <p class="text-body text-ink-mute mb-5">Run K-Means to segment employees. The system strictly maps the output into <strong>4 Operational Profiles</strong> automatically.</p>
    
    <form method="POST" action="{{ route('manager.cluster.start') }}" onsubmit="return confirm('Start clustering? This will update cluster labels for all active employees.')">
        @csrf
        <button type="submit" class="btn btn-primary px-6 py-2.5">
            <svg class="w-4 h-4 mr-2 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>RUN K-MEANS (4 CLUSTERS)</span>
        </button>
    </form>
</div>

@if(!empty($clusterAnalysis))
<div class="card p-6 mb-6">
    <div class="mb-6">
        <h2 class="text-title">Cluster Distribution</h2>
        <p class="text-caption mt-1">Characteristics of each cluster after clustering process</p>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        @foreach($clusterAnalysis as $label => $analysis)
        <div class="border border-gray-200 rounded-lg p-5 card-hover">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-12 h-12 rounded-lg flex items-center justify-center" style="background: linear-gradient(135deg, #8B5CF6 0%, #6366F1 100%);">
                    <span class="text-white font-bold text-lg mono">{{ $label }}</span>
                </div>
                <div>
                    <div class="text-tiny">CLUSTER {{ $label }}</div>
                    <div class="text-2xl font-bold text-ink mono">{{ $analysis['count'] }}</div>
                    <div class="text-caption">employees</div>
                </div>
            </div>
            
            <div class="divider mb-4"></div>
            
            <div class="space-y-2.5">
                <div class="flex justify-between items-center">
                    <span class="text-caption flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-ink-mute" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        avg_age
                    </span>
                    <span class="text-body font-semibold mono">{{ $analysis['avg_age'] }} yrs</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-caption flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-ink-mute" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        avg_salary
                    </span>
                    <span class="text-body font-semibold mono">${{ number_format($analysis['avg_salary'], 0) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-caption flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-ink-mute" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        avg_job_level
                    </span>
                    <span class="text-body font-semibold mono">{{ $analysis['avg_job_level'] }}/5</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-caption flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-ink-mute" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                        avg_rating
                    </span>
                    <span class="text-body font-semibold mono">{{ $analysis['avg_rating'] }}/5</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-caption flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-ink-mute" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        avg_satisfied
                    </span>
                    <span class="text-body font-semibold mono">{{ $analysis['avg_satisfied'] ?? 'N/A' }}/5</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-caption flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-ink-mute" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                        </svg>
                        avg_certs
                    </span>
                    <span class="text-body font-semibold mono">{{ $analysis['avg_certifications'] }}</span>
                </div>
            </div>
            
            <div class="divider my-4"></div>
            
            <div>
                <div class="text-tiny mb-2">CLUSTER INTERPRETATION</div>
                <div class="text-caption bg-gray-50 rounded-lg p-3 mono leading-relaxed">
                    @php
                        if ($label == 1) {
                            echo '<span class="text-emerald-600">● SHIFT LEADERS (A)</span><br>';
                            echo 'Senior (PG), High Level & Salary';
                        } elseif ($label == 2) {
                            echo '<span class="text-sky">● EXECUTORS (B)</span><br>';
                            echo 'Junior (UG), Lower Level & Salary';
                        } elseif ($label == 3) {
                            echo '<span class="text-purple-600">● STABILIZERS (C)</span><br>';
                            echo 'Mid-level, High Rating/Satisfied';
                        } elseif ($label == 4) {
                            echo '<span class="text-amber-600">● WATCHLIST (D)</span><br>';
                            echo 'Lower Rating/Satisfied, Risk of Burnout';
                        } else {
                            echo '<span class="text-gray-600">● UNKNOWN PROFILE</span><br>';
                            echo 'Unmapped characteristics';
                        }
                    @endphp
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif
@endsection