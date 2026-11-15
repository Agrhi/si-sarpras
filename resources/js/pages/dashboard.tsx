import { Head, Link, usePage } from '@inertiajs/react';
import { Avatar, Tag } from 'antd';
import {
    ArrowUpRight,
    CalendarDays,
    KeyRound,
    ShieldCheck,
    Users,
    UsersRound,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { index as permissionIndex } from '@/actions/App/Http/Controllers/Admin/PermissionController';
import { index as roleIndex } from '@/actions/App/Http/Controllers/Admin/RoleController';
import { index as userIndex } from '@/actions/App/Http/Controllers/Admin/UserController';
import PageHeader from '@/components/page-header';
import Panel from '@/components/panel';
import { useInitials } from '@/hooks/use-initials';
import { dashboard } from '@/routes';
import { edit as profileEdit } from '@/routes/profile';
import { edit as securityEdit } from '@/routes/security';
import { index as teamIndex } from '@/routes/teams';

type GlobalStats = {
    users: number;
    roles: number;
    permissions: number;
    teams: number;
};

type RecentUser = {
    id: number;
    name: string;
    email: string;
    roles: string[];
    created_at: string;
};

type Bucket = {
    label: string;
    count: number;
};

type SarprasSummary = {
    assets?: {
        total: number;
        byStatus: Bucket[];
        byCondition: Bucket[];
        byCategory: Bucket[];
        byLocation: Bucket[];
    };
    loans?: {
        active: number;
        dueSoon: number;
        overdue: number;
        items: {
            id: number;
            borrower: string;
            due_at: string;
            overdue_days: number;
        }[];
    };
    maintenance?: {
        open: number;
        items: {
            id: number;
            asset: string;
            urgency: { value: string; label: string };
            status: string;
            created_at: string;
        }[];
    };
    schedules?: {
        due: number;
        items: {
            id: number;
            asset: string;
            title: string;
            next_due_at: string;
            state: string;
        }[];
    };
    procurements?: {
        pending: number;
        approved: number;
        received: number;
    };
    disposals?: {
        pending: number;
        approved: number;
        completed: number;
    };
};

type Props = {
    sarpras: SarprasSummary | null;
    globalStats: GlobalStats | null;
    personal: {
        teams: number;
        roles: string[];
        unreadNotifications: number;
        memberSince: string;
    };
    recentUsers: RecentUser[];
};

type StatCard = {
    label: string;
    value: number | string;
    icon: ReactNode;
    accent: string;
};

const accentStyles: Record<string, string> = {
    indigo: 'bg-indigo-500/10 text-indigo-500',
    emerald: 'bg-emerald-500/10 text-emerald-500',
    amber: 'bg-amber-500/10 text-amber-500',
    sky: 'bg-sky-500/10 text-sky-500',
};

function StatGrid({ cards }: { cards: StatCard[] }) {
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {cards.map((card) => (
                <div
                    key={card.label}
                    className="rounded-2xl border border-border bg-card p-5 shadow-sm"
                >
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                            <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                {card.label}
                            </p>
                            <p className="mt-2 text-2xl font-semibold tracking-tight text-foreground">
                                {card.value}
                            </p>
                        </div>
                        <div
                            className={`flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl ${accentStyles[card.accent]}`}
                        >
                            {card.icon}
                        </div>
                    </div>
                </div>
            ))}
        </div>
    );
}

function StatTile({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-xl border border-border bg-muted/30 px-3 py-2.5">
            <p
                className="truncate text-xs text-muted-foreground"
                title={label}
            >
                {label}
            </p>
            <p className="mt-1 text-xl font-semibold tabular-nums text-foreground">
                {value}
            </p>
        </div>
    );
}

function BucketTiles({ title, rows }: { title: string; rows: Bucket[] }) {
    return (
        <div>
            <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                {title}
            </p>
            <div className="mt-2 space-y-2">
                {rows.map((row) => (
                    <StatTile
                        key={row.label}
                        label={row.label}
                        value={row.count}
                    />
                ))}
            </div>
        </div>
    );
}

const urgencyColors: Record<string, string> = {
    rendah: 'green',
    sedang: 'orange',
    tinggi: 'red',
};

export default function Dashboard({
    sarpras,
    globalStats,
    personal,
    recentUsers,
}: Props) {
    const page = usePage();
    const user = page.props.auth.user;
    const getInitials = useInitials();

    const dateLabel = new Date().toLocaleDateString(undefined, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    const cards: StatCard[] = globalStats
        ? [
              {
                  label: 'Total users',
                  value: globalStats.users,
                  icon: <Users size={18} />,
                  accent: 'indigo',
              },
              {
                  label: 'Roles',
                  value: globalStats.roles,
                  icon: <ShieldCheck size={18} />,
                  accent: 'emerald',
              },
              {
                  label: 'Permissions',
                  value: globalStats.permissions,
                  icon: <KeyRound size={18} />,
                  accent: 'amber',
              },
              {
                  label: 'Teams',
                  value: globalStats.teams,
                  icon: <UsersRound size={18} />,
                  accent: 'sky',
              },
          ]
        : [
              {
                  label: 'Your teams',
                  value: personal.teams,
                  icon: <UsersRound size={18} />,
                  accent: 'indigo',
              },
              {
                  label: 'Your roles',
                  value: personal.roles.length,
                  icon: <ShieldCheck size={18} />,
                  accent: 'emerald',
              },
              {
                  label: 'Unread notifications',
                  value: personal.unreadNotifications,
                  icon: <CalendarDays size={18} />,
                  accent: 'amber',
              },
              {
                  label: 'Member since',
                  value: new Date(personal.memberSince).getFullYear(),
                  icon: <CalendarDays size={18} />,
                  accent: 'sky',
              },
          ];

    const quickActions = globalStats
        ? [
              { label: 'Manage users', href: userIndex().url },
              { label: 'Manage roles', href: roleIndex().url },
              { label: 'Manage permissions', href: permissionIndex().url },
          ]
        : [
              { label: 'Your teams', href: teamIndex().url },
              { label: 'Profile settings', href: profileEdit().url },
              { label: 'Security settings', href: securityEdit().url },
          ];

    return (
        <div className="mx-auto w-full max-w-7xl space-y-6">
            <Head title="Dashboard" />
            <PageHeader
                eyebrow={dateLabel}
                title={`Welcome back, ${user.name.split(' ')[0]}`}
                description="Ringkasan akses dan aktivitas terbaru di ruang kerja kamu."
            />

            <StatGrid cards={cards} />

            {sarpras && (
                <div className="space-y-6">
                    <h2 className="text-lg font-semibold tracking-tight text-foreground">
                        Ringkasan Sarpras
                    </h2>

                    {sarpras.assets && (
                        <Panel
                            title="Aset"
                            description={`${sarpras.assets.total} aset tercatat`}
                        >
                            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
                                <BucketTiles
                                    title="Per Status"
                                    rows={sarpras.assets.byStatus}
                                />
                                <BucketTiles
                                    title="Per Kondisi"
                                    rows={sarpras.assets.byCondition}
                                />
                                <BucketTiles
                                    title="Per Kategori"
                                    rows={sarpras.assets.byCategory}
                                />
                                <BucketTiles
                                    title="Per Lokasi"
                                    rows={sarpras.assets.byLocation}
                                />
                            </div>
                        </Panel>
                    )}

                    {(sarpras.loans ||
                        sarpras.maintenance ||
                        sarpras.schedules) && (
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                            {sarpras.loans && (
                                <Panel
                                    title="Peminjaman Aktif"
                                    description={`${sarpras.loans.active} aktif · ${sarpras.loans.dueSoon} mendekati tenggat · ${sarpras.loans.overdue} terlambat`}
                                    bodyClassName="p-0 sm:p-0"
                                >
                                    {sarpras.loans.items.length === 0 ? (
                                        <p className="p-5 text-sm text-muted-foreground">
                                            Tidak ada peminjaman aktif.
                                        </p>
                                    ) : (
                                        <ul className="m-0 list-none divide-y divide-border p-0">
                                            {sarpras.loans.items.map((loan) => (
                                                <li
                                                    key={loan.id}
                                                    className="flex items-center justify-between gap-3 px-5 py-3 text-sm"
                                                >
                                                    <div className="min-w-0">
                                                        <p className="truncate font-medium text-foreground">
                                                            {loan.borrower}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            Jatuh tempo{' '}
                                                            {loan.due_at}
                                                        </p>
                                                    </div>
                                                    {loan.overdue_days > 0 && (
                                                        <Tag color="red">
                                                            {loan.overdue_days}{' '}
                                                            hari
                                                        </Tag>
                                                    )}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </Panel>
                            )}

                            {sarpras.maintenance && (
                                <Panel
                                    title="Kerusakan Belum Selesai"
                                    description={`${sarpras.maintenance.open} laporan terbuka`}
                                    bodyClassName="p-0 sm:p-0"
                                >
                                    {sarpras.maintenance.items.length === 0 ? (
                                        <p className="p-5 text-sm text-muted-foreground">
                                            Tidak ada laporan terbuka.
                                        </p>
                                    ) : (
                                        <ul className="m-0 list-none divide-y divide-border p-0">
                                            {sarpras.maintenance.items.map(
                                                (report) => (
                                                    <li
                                                        key={report.id}
                                                        className="flex items-center justify-between gap-3 px-5 py-3 text-sm"
                                                    >
                                                        <div className="min-w-0">
                                                            <p className="truncate font-medium text-foreground">
                                                                {report.asset}
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {
                                                                    report.status
                                                                }{' '}
                                                                ·{' '}
                                                                {
                                                                    report.created_at
                                                                }
                                                            </p>
                                                        </div>
                                                        <Tag
                                                            color={
                                                                urgencyColors[
                                                                    report
                                                                        .urgency
                                                                        .value
                                                                ] ?? 'default'
                                                            }
                                                        >
                                                            {
                                                                report.urgency
                                                                    .label
                                                            }
                                                        </Tag>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    )}
                                </Panel>
                            )}

                            {sarpras.schedules && (
                                <Panel
                                    title="Jadwal Jatuh Tempo"
                                    description={`${sarpras.schedules.due} jadwal perlu ditindaklanjuti`}
                                    bodyClassName="p-0 sm:p-0"
                                >
                                    {sarpras.schedules.items.length === 0 ? (
                                        <p className="p-5 text-sm text-muted-foreground">
                                            Tidak ada jadwal jatuh tempo.
                                        </p>
                                    ) : (
                                        <ul className="m-0 list-none divide-y divide-border p-0">
                                            {sarpras.schedules.items.map(
                                                (schedule) => (
                                                    <li
                                                        key={schedule.id}
                                                        className="flex items-center justify-between gap-3 px-5 py-3 text-sm"
                                                    >
                                                        <div className="min-w-0">
                                                            <p className="truncate font-medium text-foreground">
                                                                {
                                                                    schedule.asset
                                                                }
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {schedule.title}{' '}
                                                                ·{' '}
                                                                {
                                                                    schedule.next_due_at
                                                                }
                                                            </p>
                                                        </div>
                                                        <Tag
                                                            color={
                                                                schedule.state ===
                                                                'terlambat'
                                                                    ? 'red'
                                                                    : 'orange'
                                                            }
                                                        >
                                                            {schedule.state ===
                                                            'terlambat'
                                                                ? 'Terlambat'
                                                                : 'Jatuh tempo'}
                                                        </Tag>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    )}
                                </Panel>
                            )}
                        </div>
                    )}

                    {(sarpras.procurements || sarpras.disposals) && (
                        <Panel
                            title="Pengadaan & Penghapusan"
                            description="Usulan yang sedang berjalan."
                        >
                            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                {sarpras.procurements && (
                                    <div>
                                        <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                            Pengadaan
                                        </p>
                                        <div className="mt-2 grid grid-cols-3 gap-2">
                                            <StatTile
                                                label="Menunggu"
                                                value={
                                                    sarpras.procurements.pending
                                                }
                                            />
                                            <StatTile
                                                label="Disetujui"
                                                value={
                                                    sarpras.procurements.approved
                                                }
                                            />
                                            <StatTile
                                                label="Diterima"
                                                value={
                                                    sarpras.procurements.received
                                                }
                                            />
                                        </div>
                                    </div>
                                )}
                                {sarpras.disposals && (
                                    <div>
                                        <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                            Penghapusan
                                        </p>
                                        <div className="mt-2 grid grid-cols-3 gap-2">
                                            <StatTile
                                                label="Menunggu"
                                                value={
                                                    sarpras.disposals.pending
                                                }
                                            />
                                            <StatTile
                                                label="Disetujui"
                                                value={
                                                    sarpras.disposals.approved
                                                }
                                            />
                                            <StatTile
                                                label="Selesai"
                                                value={
                                                    sarpras.disposals.completed
                                                }
                                            />
                                        </div>
                                    </div>
                                )}
                            </div>
                        </Panel>
                    )}
                </div>
            )}

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div className="space-y-6 xl:col-span-2">
                    {globalStats && (
                        <Panel
                            title="Newest users"
                            description="Akun yang paling baru terdaftar."
                            actions={
                                <Link
                                    href={userIndex().url}
                                    className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                                >
                                    View all
                                    <ArrowUpRight size={13} />
                                </Link>
                            }
                            bodyClassName="p-0 sm:p-0"
                        >
                            {recentUsers.length === 0 ? (
                                <p className="p-6 text-sm text-muted-foreground">
                                    No users yet.
                                </p>
                            ) : (
                                <ul className="divide-y divide-border">
                                    {recentUsers.map((recent) => (
                                        <li
                                            key={recent.id}
                                            className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                                        >
                                            <div className="flex min-w-0 items-center gap-3">
                                                <Avatar
                                                    size={36}
                                                    style={{
                                                        background:
                                                            'linear-gradient(135deg, #6366f1, #8b5cf6)',
                                                        fontSize: 12,
                                                        fontWeight: 600,
                                                    }}
                                                >
                                                    {getInitials(recent.name)}
                                                </Avatar>
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium text-foreground">
                                                        {recent.name}
                                                    </p>
                                                    <p className="truncate text-xs text-muted-foreground">
                                                        {recent.email}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                {recent.roles.length > 0 ? (
                                                    recent.roles.map((role) => (
                                                        <Tag
                                                            key={role}
                                                            color="blue"
                                                        >
                                                            {role}
                                                        </Tag>
                                                    ))
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        No role
                                                    </span>
                                                )}
                                                <span className="text-xs text-muted-foreground">
                                                    {new Date(
                                                        recent.created_at,
                                                    ).toLocaleDateString()}
                                                </span>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Panel>
                    )}

                    <Panel
                        title="Your access"
                        description="Ringkasan role dan keanggotaan kamu."
                    >
                        <dl className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div className="rounded-xl border border-border bg-muted/30 p-4">
                                <dt className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Roles
                                </dt>
                                <dd className="mt-2 flex flex-wrap gap-1">
                                    {personal.roles.length > 0 ? (
                                        personal.roles.map((role) => (
                                            <Tag key={role} color="purple">
                                                {role}
                                            </Tag>
                                        ))
                                    ) : (
                                        <span className="text-sm text-muted-foreground">
                                            No role assigned
                                        </span>
                                    )}
                                </dd>
                            </div>
                            <div className="rounded-xl border border-border bg-muted/30 p-4">
                                <dt className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Teams
                                </dt>
                                <dd className="mt-2 text-sm font-medium text-foreground">
                                    {personal.teams} team
                                    {personal.teams === 1 ? '' : 's'}
                                </dd>
                            </div>
                            <div className="rounded-xl border border-border bg-muted/30 p-4">
                                <dt className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Notifications
                                </dt>
                                <dd className="mt-2 text-sm font-medium text-foreground">
                                    {personal.unreadNotifications} unread
                                </dd>
                            </div>
                        </dl>
                    </Panel>
                </div>

                <div className="space-y-6">
                    <Panel title="Quick actions" bodyClassName="p-2 sm:p-2">
                        <ul className="m-0 list-none p-0">
                            {quickActions.map((action) => (
                                <li key={action.label}>
                                    <Link
                                        href={action.href}
                                        className="flex items-center justify-between gap-2 rounded-xl px-4 py-3 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                                    >
                                        {action.label}
                                        <ArrowUpRight
                                            size={14}
                                            className="text-muted-foreground"
                                        />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </Panel>

                    <Panel title="Session" bodyClassName="p-4 sm:p-5">
                        <div className="space-y-3 text-sm">
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-muted-foreground">
                                    Signed in as
                                </span>
                                <span className="truncate font-medium text-foreground">
                                    {user.email}
                                </span>
                            </div>
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-muted-foreground">
                                    Member since
                                </span>
                                <span className="font-medium text-foreground">
                                    {new Date(
                                        personal.memberSince,
                                    ).toLocaleDateString()}
                                </span>
                            </div>
                            <Link
                                href={dashboard()}
                                className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                            >
                                Refresh dashboard
                                <ArrowUpRight size={13} />
                            </Link>
                        </div>
                    </Panel>
                </div>
            </div>
        </div>
    );
}

Dashboard.layout = () => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
});
