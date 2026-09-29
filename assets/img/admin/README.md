# Admin onboarding artwork

The onboarding wizard currently draws its illustrations and payment-method
marks as inline SVG in `client/admin/icons.js`. Those are placeholders sized and
coloured to the Figma frames so layout and spacing are final — only the artwork
itself is stand-in.

To swap in the real exports:

1. Drop the files here, e.g. `welcome-docs.svg`, `welcome-support.svg`,
   `setup-success.svg`, `flutterwave-wordmark.svg`, `method-opay.svg`.
2. In `client/admin/icons.js`, replace the placeholder component body with an
   image pointing at the file:

   ```js
   import { adminData } from './lib/api';

   export const DocsIllustration = () => (
       <img
           className="flw-illustration"
           src={ `${ adminData().assetsUrl }welcome-docs.svg` }
           alt=""
       />
   );
   ```

   `assetsUrl` already resolves to this directory and is passed through from
   `Flutterwave_Admin_Page::enqueue_assets()`.
3. Run `npm run build:webpack`.

Keep the marks in `METHOD_MARKS` keyed by the method keys declared in
`client/admin/lib/constants.js`, since those keys are what the PHP settings
model maps to Flutterwave payment options.
