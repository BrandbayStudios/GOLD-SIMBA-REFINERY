<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Complaint;
use App\Models\Payment;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'customers' => User::where('role', 'customer')->count(),
            'providers' => User::where('role', 'provider')->count(),
            'pending_verification' => ProviderProfile::where('verification_status', 'pending')->count(),
            'online_providers' => ProviderProfile::available()->count(),
            'active_requests' => ServiceRequest::activeJobs()->count(),
            'active_emergencies' => ServiceRequest::activeJobs()->where('priority', 'emergency')->count(),
            'completed' => ServiceRequest::where('status', 'completed')->count(),
            'cancelled' => ServiceRequest::where('status', 'cancelled')->count(),
            'revenue' => Payment::where('status', 'confirmed')->sum('amount'),
            'commission' => Payment::where('status', 'confirmed')->sum('commission_amount'),
            'pending_payments' => Payment::where('status', 'pending')->count(),
            'open_complaints' => Complaint::whereIn('status', ['open', 'investigating'])->count(),
        ];

        $emergencies = ServiceRequest::activeJobs()->with(['category', 'provider'])
            ->orderByRaw("CASE priority WHEN 'emergency' THEN 0 WHEN 'urgent' THEN 1 ELSE 2 END")->latest()->take(8)->get();
        $pendingProviders = ProviderProfile::where('verification_status', 'pending')->with('user', 'categories')->latest()->take(5)->get();
        $complaints = Complaint::whereIn('status', ['open', 'investigating'])->with('user')->latest()->take(5)->get();
        $trend = $this->dailyTrend(14);

        return view('admin.dashboard', compact('stats', 'emergencies', 'pendingProviders', 'complaints', 'trend'));
    }

    public function reports(Request $request)
    {
        [$from, $to] = $this->range($request);

        $requests = ServiceRequest::whereBetween('created_at', [$from, $to]);
        $summary = [
            'requests' => (clone $requests)->count(),
            'completed' => (clone $requests)->where('status', 'completed')->count(),
            'cancelled' => (clone $requests)->where('status', 'cancelled')->count(),
            'emergency' => (clone $requests)->where('priority', 'emergency')->count(),
        ];
        $payments = Payment::where('status', 'confirmed')->whereBetween('confirmed_at', [$from, $to]);
        $summary['revenue'] = (clone $payments)->sum('amount');
        $summary['commission'] = (clone $payments)->sum('commission_amount');
        $summary['completion_rate'] = $summary['requests'] ? round($summary['completed'] / $summary['requests'] * 100, 1) : 0;

        $byCategory = Category::withCount(['requests as total' => fn ($q) => $q->whereBetween('created_at', [$from, $to]),
            'requests as completed' => fn ($q) => $q->whereBetween('created_at', [$from, $to])->where('status', 'completed')])
            ->withSum(['requests as revenue' => fn ($q) => $q->whereBetween('created_at', [$from, $to])->where('payment_status', 'paid')], 'agreed_amount')
            ->withSum(['requests as commission' => fn ($q) => $q->whereBetween('created_at', [$from, $to])->where('payment_status', 'paid')], 'commission_amount')
            ->orderByDesc('total')->get();

        $byMethod = Payment::where('status', 'confirmed')->whereBetween('confirmed_at', [$from, $to])
            ->selectRaw('method, COUNT(*) as c, SUM(amount) as total')->groupBy('method')->get();

        $topProviders = User::where('role', 'provider')
            ->withCount(['providerJobs as jobs' => fn ($q) => $q->where('status', 'completed')->whereBetween('completed_at', [$from, $to])])
            ->withSum(['providerJobs as earned' => fn ($q) => $q->where('payment_status', 'paid')->whereBetween('completed_at', [$from, $to])], 'agreed_amount')
            ->with('providerProfile')->orderByDesc('jobs')->take(10)->get()->filter(fn ($u) => $u->jobs > 0);

        $days = max(1, min(90, (int) $from->diffInDays($to) + 1));
        $trend = $this->dailyTrend($days, $to);

        return view('admin.reports', compact('summary', 'byCategory', 'byMethod', 'topProviders', 'trend', 'from', 'to'));
    }

    public function export(Request $request)
    {
        [$from, $to] = $this->range($request);
        $rows = ServiceRequest::with(['category', 'provider', 'customer'])->whereBetween('created_at', [$from, $to])->orderBy('created_at')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reference', 'Date', 'Category', 'Priority', 'Status', 'Customer', 'Phone', 'Provider', 'Amount (ZMW)', 'Commission (ZMW)', 'Payment', 'Demo']);
            foreach ($rows as $r) {
                fputcsv($out, [$r->reference, $r->created_at->format('Y-m-d H:i'), $r->category->name, $r->priority, $r->statusLabel(),
                    $r->contact_name, $r->contact_phone, $r->provider?->name, $r->agreed_amount, $r->commission_amount, $r->payment_status, $r->is_demo ? 'yes' : 'no']);
            }
            fclose($out);
        }, 'handynow-requests-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function range(Request $request): array
    {
        $from = $request->date('from') ?? now()->subDays(29);
        $to = $request->date('to') ?? now();

        return [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()];
    }

    private function dailyTrend(int $days, ?Carbon $end = null): array
    {
        $end = ($end ?? now())->copy()->endOfDay();
        $start = $end->copy()->subDays($days - 1)->startOfDay();
        $requests = ServiceRequest::whereBetween('created_at', [$start, $end])->get(['created_at'])
            ->groupBy(fn ($r) => $r->created_at->format('Y-m-d'))->map->count();
        $revenue = Payment::where('status', 'confirmed')->whereBetween('confirmed_at', [$start, $end])->get(['confirmed_at', 'amount'])
            ->groupBy(fn ($p) => $p->confirmed_at->format('Y-m-d'))->map(fn ($g) => $g->sum('amount'));

        $out = [];
        for ($d = $start->copy(); $d <= $end; $d->addDay()) {
            $k = $d->format('Y-m-d');
            $out[] = ['date' => $d->format('d M'), 'requests' => $requests[$k] ?? 0, 'revenue' => (float) ($revenue[$k] ?? 0)];
        }

        return $out;
    }
}
