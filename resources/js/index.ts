import '../css/main.css';

export const GlitterHelpDesk = {
    identifier: 'glitter-help_desk',
};

if (typeof window !== 'undefined') {
    (window as Window & { GlitterHelpDesk?: typeof GlitterHelpDesk }).GlitterHelpDesk = GlitterHelpDesk;
}
