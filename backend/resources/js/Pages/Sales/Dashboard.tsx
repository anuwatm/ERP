import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import StatCard from '@/Components/UI/StatCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

type Summary = {
    customers: number;
    active_customers: number;
    open_deals: number;
    pipeline_value: number;
    won_deals: number;
    lost_deals: number;
    follow_ups_today: number;
    stale_deals: number;
};

type PipelineRow = {
    stage: string;
    count: number;
    value: number;
};

type OwnerRow = {
    owner: string;
    deals_count: number;
    pipeline_value: number;
};

export default function SalesDashboard({
    summary,
    pipelineByStage,
    topOwners,
}: {
    summary: Summary;
    pipelineByStage: PipelineRow[];
    topOwners: OwnerRow[];
}) {
    const cards = [
        {
            title: 'Customers',
            value: summary.customers,
            color: 'bg-indigo-50 text-indigo-600',
        },
        {
            title: 'Active Customers',
            value: summary.active_customers,
            color: 'bg-emerald-50 text-emerald-600',
        },
        {
            title: 'Open Deals',
            value: summary.open_deals,
            color: 'bg-sky-50 text-sky-600',
        },
        {
            title: 'Pipeline Value',
            value: summary.pipeline_value.toLocaleString(),
            color: 'bg-blue-50 text-blue-600',
        },
        {
            title: 'Won Deals',
            value: summary.won_deals,
            color: 'bg-green-50 text-green-600',
        },
        {
            title: 'Lost Deals',
            value: summary.lost_deals,
            color: 'bg-rose-50 text-rose-600',
        },
        {
            title: 'Follow-ups Today',
            value: summary.follow_ups_today,
            color: 'bg-amber-50 text-amber-600',
        },
        {
            title: 'Stale Deals',
            value: summary.stale_deals,
            color: 'bg-orange-50 text-orange-600',
        },
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Sales Dashboard" />

            <div className="space-y-6">
                <PageHeader
                    title="Sales Dashboard"
                    description="CRM summary, pipeline health, follow-ups, and stale deal monitor."
                    actions={<Badge variant="info">Phase 2 Sales Only</Badge>}
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {cards.map((card) => (
                        <StatCard
                            key={card.title}
                            title={card.title}
                            value={card.value}
                            iconBgColor={card.color}
                        />
                    ))}
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card
                        title="Deals Pipeline"
                        description="Deal count and value by stage"
                    >
                        <DataTable
                            data={pipelineByStage}
                            keyExtractor={(row) => row.stage}
                            columns={[
                                {
                                    header: 'Stage',
                                    accessor: (row) => (
                                        <Badge
                                            variant={
                                                row.stage === 'won'
                                                    ? 'success'
                                                    : row.stage === 'lost'
                                                      ? 'danger'
                                                      : 'info'
                                            }
                                        >
                                            {row.stage}
                                        </Badge>
                                    ),
                                },
                                {
                                    header: 'Deals',
                                    accessor: (row) => row.count,
                                },
                                {
                                    header: 'Value',
                                    accessor: (row) =>
                                        row.value.toLocaleString(),
                                },
                            ]}
                        />
                    </Card>

                    <Card
                        title="Top Sales Owners"
                        description="Open pipeline ranked by value"
                    >
                        <DataTable
                            data={topOwners}
                            keyExtractor={(row) => row.owner}
                            columns={[
                                {
                                    header: 'Owner',
                                    accessor: (row) => row.owner,
                                },
                                {
                                    header: 'Open Deals',
                                    accessor: (row) => row.deals_count,
                                },
                                {
                                    header: 'Pipeline Value',
                                    accessor: (row) =>
                                        row.pipeline_value.toLocaleString(),
                                },
                            ]}
                        />
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
