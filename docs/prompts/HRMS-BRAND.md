# Brand

Source artwork: `docs/brand/app-icon.png`. Red geometric triangle on white. Do not redraw it. Do not recolour the file. Do not place it on a red square.

## Tokens

Today in `public/assets/css/app.css`:

```css
--bg:#f7f7fa; --sf:#fff; --bd:#e9e9f0; --tx:#17171f; --mu:#6e6e80;
--ac:#5b4bdb; --as:#efecfd;
--ok:#12805c; --oks:#e5f5ee; --wn:#a85a06; --wns:#fdf1de; --er:#c4283c; --ers:#fdecee;
```

Move the brand accent off purple onto the icon red. Keep `--ok`, `--wn`, and `--er` so Present, Late, and Absent do not change meaning.

- `--ac` icon red, about `#E10613` (sample the PNG)
- `--as` pale red wash, about `#FDECEE`
- `--glass` `rgba(255,255,255,.72)`
- `--glass-bd` `rgba(255,255,255,.55)`
- `--glass-blur` `blur(22px) saturate(160%)`

Inter stays. Radii stay in the current 8–16px range. No second font. No dark theme.

The letter tile in `.logo` becomes this mark. Login and two-factor use the same mark and the same red accent. There is no separate Branding screen. Company name and logo stay on the Companies master if needed.
