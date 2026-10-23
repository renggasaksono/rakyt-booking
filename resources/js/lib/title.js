/**
 * Browser tab titles. App pages get " · Rakyt Booking" (the product name); public
 * pages already pass the full server title ("Kencana Trip · Rakyt Booking",
 * "Karimunjawa 3H2M · Kencana Trip"), recognised by its " · " separator, and keep
 * it as is (ADR-0024 §2).
 */
export function pageTitle(title) {
    if (!title) {
        return 'Rakyt Booking';
    }

    return title.includes(' · ') ? title : `${title} · Rakyt Booking`;
}
