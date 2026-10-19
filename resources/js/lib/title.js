/**
 * Browser tab titles. App pages get " · Rakyt"; public pages already pass the
 * full server title ("Kencana Trip · Rakyt", "Karimunjawa 3H2M · Kencana Trip"),
 * recognised by its " · " separator, and keep it as is (ADR-0024 §2).
 */
export function pageTitle(title) {
    if (!title) {
        return 'Rakyt';
    }

    return title.includes(' · ') ? title : `${title} · Rakyt`;
}
