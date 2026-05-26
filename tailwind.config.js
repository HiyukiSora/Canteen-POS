/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./public/**/*.{php,html,js}",
    "./src/**/*.css"
  ],
  theme: {
    extend: {},
  },
  plugins: [require("daisyui")],
  daisyui: {
    themes: ["light", "dark", "business"],
    darkTheme: "business",
    base: true,
    styled: true,
    utils: true,
  },
}