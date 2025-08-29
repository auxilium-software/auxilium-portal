let lfsInstanceFqdn = document.getElementById("lfs_instance_fqdn").textContent;
let thisObject = document.getElementById("object_uuid").textContent;

let attachToObject = (objectPath, type = "global") =>
{
    let se = new SearchEngine(type, 8);
    let fp = se.fullPage();
    document.getElementById("portal-authenticated-user-bar").after(fp);
    fp.showModal();
    se.onSelect((path) =>
    {
        query('LINK ' + path + ' AS ' + objectPath, () =>
        {
            location.reload();
        });
        //fp.remove();
    });
    se.focus();
}
