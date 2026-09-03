import extractionRoutes from '@/routes/api/extractions';
import { apiRequest } from '@/shared/api/http';
import type { DocumentExtraction } from './types';

type Response = { data: DocumentExtraction[] };
type ItemResponse = { data: DocumentExtraction };

export const extractionApi = {
    async list(): Promise<Response> {
        return apiRequest<Response>(extractionRoutes.index.url());
    },
    async upload(file: File): Promise<DocumentExtraction> {
        const body = new FormData();
        body.append('document', file);

        return (
            await apiRequest<ItemResponse>(extractionRoutes.store.url(), {
                method: 'POST',
                body,
            })
        ).data;
    },
    async show(id: number): Promise<DocumentExtraction> {
        return (await apiRequest<ItemResponse>(extractionRoutes.show.url(id)))
            .data;
    },
    async retry(id: number): Promise<DocumentExtraction> {
        return (
            await apiRequest<ItemResponse>(extractionRoutes.retry.url(id), {
                method: 'POST',
            })
        ).data;
    },
    async remove(id: number): Promise<void> {
        await apiRequest(extractionRoutes.destroy.url(id), {
            method: 'DELETE',
        });
    },
    sourceUrl: (id: number): string => extractionRoutes.download.url(id),
    jsonUrl: (id: number): string => extractionRoutes.downloadJson.url(id),
};
