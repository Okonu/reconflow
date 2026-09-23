import { Head } from '@inertiajs/react';
import { Download, Package } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { ResetDemoCard } from '../../components/ResetDemoCard';
import { UploadForm } from '../../components/UploadForm';
import { UploadHistory } from '../../components/UploadHistory';
import type { SourceOption, StagedUpload } from '../../types';

interface Props {
    sources: SourceOption[];
    history: { data: StagedUpload[] };
    default_business_date: string;
    can: { upload: boolean; reset: boolean };
}

export default function UploadsIndex({ sources, history, default_business_date, can }: Props) {
    return (
        <AppLayout>
            <Head title="Data uploads" />
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold">Data uploads</h1>
                    <p className="text-sm text-muted-foreground">Upload sales, payment or ERP files. Nothing is imported until you review the preview and confirm.</p>
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle>Templates and sample data</CardTitle>
                        <CardDescription>Templates are generated from the same rules the importer checks against.</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-2">
                        {sources.map((source) => (
                            <Button key={source.value} variant="outline" asChild>
                                <a href={route('ingestion.uploads.template', source.value)}>
                                    <Download /> {source.label} template
                                </a>
                            </Button>
                        ))}
                        <Button variant="secondary" asChild>
                            <a href={route('ingestion.uploads.sample-pack')}>
                                <Package /> Download sample test pack
                            </a>
                        </Button>
                    </CardContent>
                </Card>
                {can.upload && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Upload a file</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <UploadForm sources={sources} defaultDate={default_business_date} />
                        </CardContent>
                    </Card>
                )}
                <Card>
                    <CardHeader>
                        <CardTitle>Upload history</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <UploadHistory uploads={history.data} />
                    </CardContent>
                </Card>
                {can.reset && <ResetDemoCard />}
            </div>
        </AppLayout>
    );
}
